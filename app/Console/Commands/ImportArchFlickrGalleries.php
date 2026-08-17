<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportArchFlickrGalleries extends Command
{
    protected $signature = 'import:arch-flickr-galleries
        {--commit : Download public Flickr photos and write galleries, images and relations}
        {--limit= : Limit number of archive Flickr galleries}
        {--gallery-id=* : Import only selected archive gallery IDs}
        {--web : Read public Flickr album pages instead of using the API key}
        {--stop-on-error : Stop on the first failed gallery}';

    protected $description = 'Import public legacy Flickr galleries into S3 and the current database.';

    private bool $commit = false;
    private string $archiveConnection;
    private array $stats = [
        'galleries_seen' => 0, 'galleries_created' => 0, 'galleries_existing' => 0,
        'photos_seen' => 0, 'photos_downloaded' => 0, 'photos_existing' => 0,
        'images_created' => 0, 'images_existing' => 0, 'thumbnails_created' => 0,
        'article_relations_created' => 0, 'article_relations_existing' => 0,
        'unavailable_albums' => 0, 'errors' => 0,
    ];

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');
        $this->archiveConnection = config('database.archive_connection', 'arch_mysql');
        $apiKey = trim((string) env('FLICKR_API_KEY'));
        $webMode = (bool) $this->option('web');

        if ($apiKey === '' && !$webMode) {
            $this->error('Configure FLICKR_API_KEY or run with --web for public albums.');
            return self::FAILURE;
        }
        if (!$this->requiredTablesExist()) {
            $this->error('Required target tables are missing. Run migrations first.');
            return self::FAILURE;
        }

        $this->warn($this->commit ? 'WRITE MODE: public Flickr photos will be downloaded to S3.' : 'DRY RUN: no data or files will be written.');
        if ($webMode) $this->warn('WEB MODE: imports only files publicly exposed by Flickr album pages.');
        $query = DB::connection($this->archiveConnection)->table('spbg_glr')
            ->select(['id', 'name', 'album', 'userid'])
            ->where('kind', 'flickr')
            ->whereNotNull('album')->where('album', '<>', '')
            ->whereNotNull('userid')->where('userid', '<>', '');

        $ids = array_filter(array_map('intval', (array) $this->option('gallery-id')));
        if ($ids !== []) $query->whereIn('id', $ids);
        if (($limit = $this->option('limit')) !== null && $limit !== '') $query->limit((int) $limit);

        $this->info('Archive Flickr galleries selected: '.(clone $query)->count());
        $query->orderBy('id')->chunkById(25, function ($galleries) use ($apiKey, $webMode) {
            foreach ($galleries as $gallery) {
                $this->stats['galleries_seen']++;
                try {
                    $this->importGallery($gallery, $webMode ? null : $apiKey);
                } catch (Throwable $e) {
                    $this->stats['errors']++;
                    $this->error("Gallery {$gallery->id}: {$e->getMessage()}");
                    if ($this->option('stop-on-error')) throw $e;
                }
            }
        }, 'id');

        $this->table(['Metric', 'Value'], collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function importGallery(object $archive, ?string $apiKey): void
    {
        $photos = $apiKey === null
            ? $this->webPhotos((string) $archive->userid, (string) $archive->album)
            : $this->flickrPhotos((string) $archive->userid, (string) $archive->album, $apiKey);
        if ($photos === []) {
            $this->stats['unavailable_albums']++;
            $this->warn("No public Flickr photos: {$archive->id}");
            return;
        }

        $map = DB::table('arch_gallery_import_maps')->where('old_gallery_id', $archive->id)->first();
        if ($map) {
            $galleryId = (int) $map->new_gallery_id;
            $this->stats['galleries_existing']++;
        } elseif (!$this->commit) {
            $galleryId = 0;
            $this->stats['galleries_created']++;
        } else {
            $galleryId = (int) Gallery::create(['title' => mb_substr(trim((string) $archive->name) ?: "Flickr {$archive->id}", 0, 255)])->id;
            $this->stats['galleries_created']++;
        }

        $imageIds = [];
        foreach ($photos as $photo) {
            $this->stats['photos_seen']++;
            $path = 'galleries/flickr/'.$archive->id.'/'.$photo['id'].'.'.$photo['extension'];
            if (!$this->commit) {
                $this->stats['photos_downloaded']++;
                $this->stats['images_created']++;
                continue;
            }
            $disk = Storage::disk('public');
            if (!$disk->exists($path)) {
                $response = Http::retry(3, 1000)->timeout(60)->get($photo['url']);
                if (!$response->successful() || $response->body() === '') throw new \RuntimeException("Download failed for Flickr photo {$photo['id']}");
                $disk->put($path, $response->body());
                if (!$disk->exists($path)) throw new \RuntimeException("S3 verification failed for Flickr photo {$photo['id']}");
                $this->stats['photos_downloaded']++;
            } else {
                $this->stats['photos_existing']++;
            }
            $image = Image::query()->firstOrCreate(['gallery_id' => $galleryId, 'image' => $path], ['title' => mb_substr((string) ($photo['title'] ?: $photo['id']), 0, 255)]);
            $image->wasRecentlyCreated ? $this->stats['images_created']++ : $this->stats['images_existing']++;
            $imageIds[] = (int) $image->id;
            $this->ensureThumbnail($path);
        }

        if ($this->commit && $galleryId > 0) {
            $cover = $imageIds[0] ?? 0;
            if ($cover > 0) Gallery::query()->whereKey($galleryId)->update(['image_id' => $cover, 'image' => Image::find($cover)?->image]);
            DB::table('arch_gallery_import_maps')->updateOrInsert(['old_gallery_id' => $archive->id], [
                'new_gallery_id' => $galleryId, 'album' => (string) $archive->album, 'images_count' => count($photos),
                'status' => 'imported_flickr', 'errors' => null, 'updated_at' => now(), 'created_at' => now(),
            ]);
            $this->attachToArticles((int) $archive->id, $galleryId);
        }
    }

    private function flickrPhotos(string $userId, string $albumId, string $apiKey): array
    {
        $result = []; $page = 1;
        do {
            $response = Http::retry(3, 1000)->timeout(30)->get('https://www.flickr.com/services/rest/', [
                'method' => 'flickr.photosets.getPhotos', 'api_key' => $apiKey, 'photoset_id' => $albumId,
                'user_id' => $userId, 'extras' => 'url_o,url_l,url_c,url_m,original_format', 'per_page' => 500,
                'page' => $page, 'format' => 'json', 'nojsoncallback' => 1,
            ]);
            if (!$response->successful()) throw new \RuntimeException("Flickr API HTTP {$response->status()}");
            $data = $response->json();
            if (($data['stat'] ?? 'fail') !== 'ok') return [];
            foreach (($data['photoset']['photo'] ?? []) as $photo) {
                $url = $photo['url_o'] ?? $photo['url_l'] ?? $photo['url_c'] ?? $photo['url_m'] ?? null;
                if (!is_string($url) || !$this->isFlickrStaticUrl($url)) continue;
                $extension = strtolower((string) pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
                $result[] = ['id' => (string) $photo['id'], 'url' => $url, 'extension' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $extension : 'jpg', 'title' => (string) ($photo['title'] ?? '')];
            }
            $page++;
        } while ($page <= (int) ($data['photoset']['pages'] ?? 1));
        return $result;
    }

    private function webPhotos(string $userId, string $albumId): array
    {
        $albumUrl = 'https://www.flickr.com/photos/'.rawurlencode($userId).'/albums/'.rawurlencode($albumId);
        $response = Http::retry(3, 1000)->timeout(30)->get($albumUrl);
        if (!$response->successful()) throw new \RuntimeException("Flickr album HTTP {$response->status()}");

        $pattern = '~/?photos/'.preg_quote($userId, '~').'/([0-9]+)/in/album-'.preg_quote($albumId, '~').'/?~';
        preg_match_all($pattern, $response->body(), $matches);
        $photoIds = array_values(array_unique($matches[1] ?? []));
        $result = [];
        foreach ($photoIds as $photoId) {
            $sizes = Http::retry(3, 1000)->timeout(30)->get('https://www.flickr.com/photos/'.rawurlencode($userId).'/'.$photoId.'/sizes/l/');
            if (!$sizes->successful()) continue;
            preg_match_all('~https://live\\.staticflickr\\.com/[^"\\s]+/'.$photoId.'_[^"\\s]+?_[bm]\\.jpg~', $sizes->body(), $urls);
            $urls = array_values(array_unique($urls[0] ?? []));
            $url = collect($urls)->first(fn (string $candidate) => str_ends_with($candidate, '_b.jpg')) ?? ($urls[0] ?? null);
            if (is_string($url) && $this->isFlickrStaticUrl($url)) $result[] = ['id' => $photoId, 'url' => $url, 'extension' => 'jpg', 'title' => $photoId];
        }
        return $result;
    }

    private function isFlickrStaticUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return str_starts_with($url, 'https://') && ($host === 'live.staticflickr.com' || str_ends_with($host, '.staticflickr.com'));
    }

    private function ensureThumbnail(string $path): void
    {
        $thumbPath = preg_replace('~^(.*/)([^/]+)$~', '$1thmb_$2', $path);
        $disk = Storage::disk('public');
        if ($disk->exists($thumbPath)) return;
        $source = @imagecreatefromstring((string) $disk->get($path));
        if (!$source) throw new \RuntimeException("Could not open image {$path}");
        $width = imagesx($source); $height = imagesy($source); $targetWidth = min(300, $width);
        $thumb = imagecreatetruecolor($targetWidth, max(1, (int) round($height * $targetWidth / max(1, $width))));
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetWidth, imagesy($thumb), $width, $height);
        ob_start(); imagejpeg($thumb, null, 80); $contents = (string) ob_get_clean();
        imagedestroy($source); imagedestroy($thumb); $disk->put($thumbPath, $contents); $this->stats['thumbnails_created']++;
    }

    private function attachToArticles(int $oldGalleryId, int $galleryId): void
    {
        $oldArticleIds = DB::connection($this->archiveConnection)->table('spbg_linked')->where('id_foto', $oldGalleryId)->where('id_article', '>', 0)->pluck('id_article')->unique();
        foreach ($oldArticleIds as $oldArticleId) {
            $articleId = DB::table('arch_article_import_maps')->where('old_article_id', $oldArticleId)->value('new_article_id');
            if (!$articleId) continue;
            $exists = DB::table('articleables')->where('article_id', $articleId)->where('articleable_type', Gallery::class)->where('articleable_id', $galleryId)->exists();
            if ($exists) { $this->stats['article_relations_existing']++; continue; }
            DB::table('articleables')->insert(['article_id' => $articleId, 'articleable_type' => Gallery::class, 'articleable_id' => $galleryId]);
            $this->stats['article_relations_created']++;
        }
    }

    private function requiredTablesExist(): bool
    {
        return Schema::hasTable('galleries') && Schema::hasTable('images') && Schema::hasTable('articleables') && Schema::hasTable('arch_gallery_import_maps') && Schema::hasTable('arch_article_import_maps');
    }
}
