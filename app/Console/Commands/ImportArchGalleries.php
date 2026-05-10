<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportArchGalleries extends Command
{
    protected $signature = 'import:arch-galleries
        {--commit : Write galleries, images, thumbnails and article relations}
        {--limit= : Limit number of archive galleries}
        {--gallery-id=* : Import only selected archive gallery IDs}
        {--article-id=* : Import only galleries linked to selected archive article IDs}
        {--force-thumbnails : Recreate thumbnails even if they already exist}
        {--stop-on-error : Stop import on first failed gallery}';

    protected $description = 'Import archive galleries from arch.spbg_glr and S3 galleries folders into current galleries/images.';

    private bool $commit = false;

    private string $archiveConnection;

    private array $stats = [
        'galleries_seen' => 0,
        'galleries_created' => 0,
        'galleries_existing' => 0,
        'folders_missing' => 0,
        'images_seen' => 0,
        'images_created' => 0,
        'images_existing' => 0,
        'thumbnails_created' => 0,
        'thumbnails_existing' => 0,
        'article_relations_created' => 0,
        'article_relations_existing' => 0,
        'article_relations_missing_map' => 0,
        'errors' => 0,
    ];

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');
        $this->archiveConnection = config('database.archive_connection', 'arch_mysql');

        $this->warn($this->commit
            ? 'WRITE MODE: данные будут записаны в текущую базу и S3.'
            : 'DRY RUN: данные не будут записаны. Для записи добавь --commit.'
        );

        if (!$this->requiredTablesExist()) {
            $this->error('Не найдены нужные таблицы. Выполни миграции и проверь arch_article_import_maps.');

            return self::FAILURE;
        }

        $query = $this->archiveGalleriesQuery();
        $total = (int) (clone $query)->count();
        $this->info("Archive galleries selected: {$total}");

        $query->orderBy('id')->chunk(100, function ($galleries) {
            foreach ($galleries as $archiveGallery) {
                $this->stats['galleries_seen']++;

                try {
                    $this->processGallery($archiveGallery);
                } catch (Throwable $exception) {
                    $this->stats['errors']++;
                    Log::error('Archive gallery import failed', [
                        'old_gallery_id' => $archiveGallery->id ?? null,
                        'album' => $archiveGallery->album ?? null,
                        'message' => $exception->getMessage(),
                    ]);

                    $this->error("Gallery {$archiveGallery->id}: {$exception->getMessage()}");

                    if ($this->option('stop-on-error')) {
                        throw $exception;
                    }
                }
            }
        });

        $this->printStats();

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function archiveGalleriesQuery()
    {
        $query = DB::connection($this->archiveConnection)
            ->table('spbg_glr')
            ->select(['id', 'id_reg', 'name', 'kind', 'userid', 'album', 'author', 'id_site', 'folder'])
            ->where('kind', 'nano_photos_provider2');

        $galleryIds = array_filter(array_map('intval', (array) $this->option('gallery-id')));
        if ($galleryIds !== []) {
            $query->whereIn('id', $galleryIds);
        }

        $articleIds = array_filter(array_map('intval', (array) $this->option('article-id')));
        if ($articleIds !== []) {
            $query->whereIn('id', function ($subQuery) use ($articleIds) {
                $subQuery->from('spbg_linked')
                    ->select('id_foto')
                    ->whereIn('id_article', $articleIds)
                    ->whereNotNull('id_foto')
                    ->where('id_foto', '>', 0);
            });
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $query->limit((int) $limit);
        }

        return $query;
    }

    private function processGallery(object $archiveGallery): void
    {
        $album = trim((string) ($archiveGallery->album ?: $archiveGallery->folder));
        if ($album === '') {
            throw new \RuntimeException('Empty album/folder field');
        }

        $galleryPath = "galleries/{$album}";
        $files = $this->galleryImageFiles($galleryPath);

        if ($files === []) {
            $this->stats['folders_missing']++;
            $this->warn("No images found: {$galleryPath}");

            return;
        }

        $map = DB::table('arch_gallery_import_maps')
            ->where('old_gallery_id', $archiveGallery->id)
            ->first();

        if ($map) {
            $this->stats['galleries_existing']++;
            $galleryId = (int) $map->new_gallery_id;
        } else {
            $galleryId = $this->createGallery($archiveGallery, $album);
        }

        $createdImageIds = [];
        foreach ($files as $filePath) {
            $this->stats['images_seen']++;
            $imageId = $this->upsertImage($galleryId, $filePath);
            if ($imageId > 0) {
                $createdImageIds[] = $imageId;
            }

            $this->ensureThumbnail($filePath);
        }

        if ($this->commit && $galleryId > 0) {
            $coverImageId = $createdImageIds[0] ?? (int) Image::query()
                ->where('gallery_id', $galleryId)
                ->where('image', $files[0])
                ->value('id');

            if ($coverImageId > 0) {
                $this->updateGalleryCover($galleryId, $coverImageId, $files[0]);
            }

            $this->upsertImportMap($archiveGallery, $galleryId, $album, count($files), 'imported');
        }

        $this->attachGalleryToImportedArticles((int) $archiveGallery->id, $galleryId);
    }

    private function createGallery(object $archiveGallery, string $album): int
    {
        $title = trim((string) ($archiveGallery->name ?: $album));

        if (!$this->commit) {
            $this->stats['galleries_created']++;

            return 0;
        }

        $gallery = Gallery::create([
            'title' => mb_substr($title, 0, 255),
        ]);

        $this->stats['galleries_created']++;

        return (int) $gallery->id;
    }

    private function upsertImage(int $galleryId, string $filePath): int
    {
        if (!$this->commit || $galleryId <= 0) {
            $this->stats['images_created']++;

            return 0;
        }

        $existing = Image::query()
            ->where('gallery_id', $galleryId)
            ->where('image', $filePath)
            ->first();

        if ($existing) {
            $this->stats['images_existing']++;

            return (int) $existing->id;
        }

        $image = Image::create([
            'title' => pathinfo($filePath, PATHINFO_FILENAME),
            'image' => $filePath,
            'gallery_id' => $galleryId,
        ]);

        $this->stats['images_created']++;

        return (int) $image->id;
    }

    private function ensureThumbnail(string $filePath): void
    {
        $thumbnailPath = $this->thumbnailPath($filePath);
        $disk = Storage::disk('public');

        if (!$this->option('force-thumbnails') && $disk->exists($thumbnailPath)) {
            $this->stats['thumbnails_existing']++;

            return;
        }

        if (!$this->commit) {
            $this->stats['thumbnails_created']++;

            return;
        }

        $contents = $disk->get($filePath);
        $source = $contents ? @imagecreatefromstring($contents) : false;
        if (!$source) {
            throw new \RuntimeException("Could not open image {$filePath}");
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $thumbWidth = min(300, $width);
        $thumbHeight = max(1, (int) round($height * ($thumbWidth / max(1, $width))));
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        $encoded = $this->encodeImage($thumb, $filePath);
        imagedestroy($source);
        imagedestroy($thumb);

        $disk->put($thumbnailPath, $encoded);
        $this->stats['thumbnails_created']++;
    }

    private function encodeImage($image, string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        ob_start();
        match ($extension) {
            'png' => imagepng($image, null, 8),
            'gif' => imagegif($image),
            'webp' => imagewebp($image, null, 80),
            default => imagejpeg($image, null, 80),
        };

        return (string) ob_get_clean();
    }

    private function attachGalleryToImportedArticles(int $oldGalleryId, int $galleryId): void
    {
        $links = DB::connection($this->archiveConnection)
            ->table('spbg_linked')
            ->where('id_foto', $oldGalleryId)
            ->whereNotNull('id_article')
            ->where('id_article', '>', 0)
            ->pluck('id_article')
            ->unique();

        foreach ($links as $oldArticleId) {
            $newArticleId = DB::table('arch_article_import_maps')
                ->where('old_article_id', $oldArticleId)
                ->value('new_article_id');

            if (!$newArticleId) {
                $this->stats['article_relations_missing_map']++;
                continue;
            }

            if (!$this->commit || $galleryId <= 0) {
                $this->stats['article_relations_created']++;
                continue;
            }

            $exists = DB::table('articleables')
                ->where('article_id', $newArticleId)
                ->where('articleable_type', Gallery::class)
                ->where('articleable_id', $galleryId)
                ->exists();

            if ($exists) {
                $this->stats['article_relations_existing']++;
                continue;
            }

            DB::table('articleables')->insert([
                'article_id' => $newArticleId,
                'articleable_type' => Gallery::class,
                'articleable_id' => $galleryId,
            ]);

            $this->stats['article_relations_created']++;
        }
    }

    private function galleryImageFiles(string $galleryPath): array
    {
        return collect(Storage::disk('public')->files($galleryPath))
            ->filter(fn (string $path) => !$this->isThumbnail($path))
            ->filter(fn (string $path) => preg_match('~\.(jpe?g|png|gif|webp)$~i', $path))
            ->sort()
            ->values()
            ->all();
    }

    private function updateGalleryCover(int $galleryId, int $imageId, string $imagePath): void
    {
        Gallery::query()
            ->whereKey($galleryId)
            ->update([
                'image_id' => $imageId,
                'image' => $imagePath,
            ]);
    }

    private function upsertImportMap(object $archiveGallery, int $galleryId, string $album, int $imagesCount, string $status): void
    {
        DB::table('arch_gallery_import_maps')->updateOrInsert(
            ['old_gallery_id' => $archiveGallery->id],
            [
                'new_gallery_id' => $galleryId,
                'album' => $album,
                'images_count' => $imagesCount,
                'status' => $status,
                'errors' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function thumbnailPath(string $filePath): string
    {
        return preg_replace('~^(.*/)([^/]+)$~', '$1thmb_$2', $filePath);
    }

    private function isThumbnail(string $filePath): bool
    {
        return str_starts_with(basename($filePath), 'thmb_');
    }

    private function requiredTablesExist(): bool
    {
        return Schema::hasTable('galleries')
            && Schema::hasTable('images')
            && Schema::hasTable('articleables')
            && Schema::hasTable('arch_article_import_maps')
            && Schema::hasTable('arch_gallery_import_maps');
    }

    private function printStats(): void
    {
        $this->newLine();
        $this->table(['Metric', 'Value'], collect($this->stats)
            ->map(fn (int $value, string $key) => [$key, $value])
            ->values()
            ->all());
    }
}
