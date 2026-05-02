<?php

namespace App\Console\Commands;

use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ImportArchArticles extends Command
{
    protected $signature = 'import:arch-articles
        {--commit : Write imported articles, videos and relations to the current database}
        {--limit= : Limit number of archive articles}
        {--offset=0 : Offset for archive articles}
        {--chunk=200 : Archive articles chunk size}
        {--article-id=* : Import only selected archive article IDs}
        {--region-id= : Force one region_id for imported articles}
        {--use-archive-region : Copy spbg_articles.id_reg into articles.region_id}
        {--update-existing : Update articles and videos already imported through mapping tables}
        {--skip-videos : Import articles only}
        {--only-public : Import only archive articles where public = 1}
        {--stop-on-error : Stop import on first failed article}';

    protected $description = 'Safely import archive spbg_articles and linked YouTube videos into articles/videos.';

    private bool $commit = false;

    private string $archiveConnection;

    private array $stats = [
        'articles_seen' => 0,
        'articles_created' => 0,
        'articles_updated' => 0,
        'articles_skipped' => 0,
        'videos_created' => 0,
        'videos_updated' => 0,
        'videos_skipped' => 0,
        'relations_created' => 0,
        'relations_existing' => 0,
        'video_parse_errors' => 0,
        'errors' => 0,
    ];

    private array $dryRunVideoIds = [];

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');
        $this->archiveConnection = config('database.archive_connection', 'arch_mysql');

        Log::info('Starting archive articles import', [
            'commit' => $this->commit,
            'archive_connection' => $this->archiveConnection,
            'limit' => $this->option('limit'),
            'offset' => $this->option('offset'),
            'chunk' => $this->option('chunk'),
            'article_ids' => $this->option('article-id'),
        ]);

        $this->warn($this->commit
            ? 'WRITE MODE: данные будут записаны в текущую базу.'
            : 'DRY RUN: данные не будут записаны. Для записи добавь --commit.'
        );

        if (!$this->requiredTablesExist()) {
            $this->error('Не найдены служебные таблицы импорта. Сначала выполни: php artisan migrate');

            return self::FAILURE;
        }

        $query = $this->archiveArticlesQuery();
        $total = $this->selectedArticlesCount(clone $query);
        $this->info("Archive articles selected: {$total}");

        $chunkSize = max(1, (int) $this->option('chunk'));

        $query->orderBy('id')->chunk($chunkSize, function ($articles) {
            foreach ($articles as $archiveArticle) {
                $this->stats['articles_seen']++;

                try {
                    $this->processArticle($archiveArticle);
                } catch (Throwable $exception) {
                    $this->stats['errors']++;
                    Log::error('Archive article import failed', [
                        'old_article_id' => $archiveArticle->id ?? null,
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);

                    $this->error("Article {$archiveArticle->id}: {$exception->getMessage()}");

                    if ($this->option('stop-on-error')) {
                        throw $exception;
                    }
                }
            }
        });

        $this->printStats();

        Log::info('Archive articles import finished', $this->stats + [
            'commit' => $this->commit,
        ]);

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function archiveArticlesQuery()
    {
        $query = DB::connection($this->archiveConnection)
            ->table('spbg_articles')
            ->select([
                'id',
                'id_reg',
                'data',
                'title',
                'intro',
                'article',
                'public',
                'lnk_foto_text',
                'lnk_foto_lnk',
            ]);

        $articleIds = array_filter(array_map('intval', (array) $this->option('article-id')));
        if ($articleIds !== []) {
            $query->whereIn('id', $articleIds);
        }

        if ($this->option('only-public')) {
            $query->where('public', 1);
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $query->limit((int) $limit);
        }

        $offset = (int) $this->option('offset');
        if ($offset > 0) {
            $query->offset($offset);
        }

        return $query;
    }

    private function processArticle(object $archiveArticle): void
    {
        Log::debug('Processing archive article', ['old_article_id' => $archiveArticle->id]);

        $existingMap = DB::table('arch_article_import_maps')
            ->where('old_article_id', $archiveArticle->id)
            ->first();

        if ($existingMap && !$this->articleExists((int) $existingMap->new_article_id)) {
            throw new \RuntimeException("Import map exists, but article {$existingMap->new_article_id} was not found");
        }

        if ($existingMap && !$this->option('update-existing')) {
            $this->stats['articles_skipped']++;
            $this->processLinkedVideos($archiveArticle->id, (int) $existingMap->new_article_id);
            return;
        }

        $payload = $this->articlePayload($archiveArticle, $existingMap ? (int) $existingMap->new_article_id : null);

        if (!$this->commit) {
            $existingMap
                ? $this->stats['articles_updated']++
                : $this->stats['articles_created']++;

            $this->processLinkedVideos($archiveArticle->id, $existingMap ? (int) $existingMap->new_article_id : 0);
            return;
        }

        DB::transaction(function () use ($archiveArticle, $existingMap, $payload) {
            if ($existingMap) {
                DB::table('articles')
                    ->where('id', $existingMap->new_article_id)
                    ->update($payload + ['updated_at' => now()]);

                $articleId = (int) $existingMap->new_article_id;
                $this->stats['articles_updated']++;
            } else {
                $articleId = (int) DB::table('articles')->insertGetId($payload + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('arch_article_import_maps')->insert([
                    'old_article_id' => $archiveArticle->id,
                    'new_article_id' => $articleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->stats['articles_created']++;
            }

            $this->processLinkedVideos($archiveArticle->id, $articleId);
        });
    }

    private function articlePayload(object $archiveArticle, ?int $existingArticleId = null): array
    {
        $title = trim((string) $archiveArticle->title);

        return [
            'region_id' => $this->resolveRegionId($archiveArticle),
            'title' => $this->limitString($title !== '' ? $title : "Статья {$archiveArticle->id}"),
            'data' => $this->normalizeDate($archiveArticle->data),
            'slug' => $this->makeUniqueSlug($title, (int) $archiveArticle->id, $existingArticleId),
            'description' => $this->nullableText($archiveArticle->intro),
            'content' => $this->nullableText($archiveArticle->article),
            'photo_info' => $this->makePhotoInfo($archiveArticle->lnk_foto_text, $archiveArticle->lnk_foto_lnk),
            'published' => 1,
            'views' => 0,
            'image' => "articles/articles{$archiveArticle->id}.jpg",
        ];
    }

    private function processLinkedVideos(int $oldArticleId, int $newArticleId): void
    {
        if ($this->option('skip-videos')) {
            return;
        }

        $linkedVideos = DB::connection($this->archiveConnection)
            ->table('spbg_linked')
            ->join('spbg_video', 'spbg_video.id', '=', 'spbg_linked.id_video')
            ->where('spbg_linked.id_article', $oldArticleId)
            ->whereNotNull('spbg_linked.id_video')
            ->where('spbg_linked.id_video', '>', 0)
            ->select([
                'spbg_video.id',
                'spbg_video.name',
                'spbg_video.video_src',
            ])
            ->distinct()
            ->get();

        foreach ($linkedVideos as $archiveVideo) {
            $src = $this->extractYoutubeEmbedUrl((string) $archiveVideo->video_src);

            if ($src === null) {
                $this->stats['video_parse_errors']++;
                Log::warning('Could not parse YouTube URL from archive video', [
                    'old_article_id' => $oldArticleId,
                    'old_video_id' => $archiveVideo->id,
                ]);
                continue;
            }

            $videoId = $this->upsertVideo($archiveVideo, $src);

            if ($this->commit && $newArticleId > 0 && $videoId > 0) {
                $this->attachVideoToArticle($newArticleId, $videoId);
            } elseif (!$this->commit) {
                $this->stats['relations_created']++;
            }
        }
    }

    private function upsertVideo(object $archiveVideo, string $src): int
    {
        $existingMap = DB::table('arch_video_import_maps')
            ->where('old_video_id', $archiveVideo->id)
            ->first();

        if ($existingMap && !$this->videoExists((int) $existingMap->new_video_id)) {
            throw new \RuntimeException("Import map exists, but video {$existingMap->new_video_id} was not found");
        }

        if ($existingMap && !$this->option('update-existing')) {
            $this->stats['videos_skipped']++;
            return (int) $existingMap->new_video_id;
        }

        $payload = [
            'title' => $this->limitString(trim((string) $archiveVideo->name) ?: "Видео {$archiveVideo->id}"),
            'src' => $src,
        ];

        if (!$this->commit) {
            if (!isset($this->dryRunVideoIds[$archiveVideo->id])) {
                $existingMap
                    ? $this->stats['videos_updated']++
                    : $this->stats['videos_created']++;

                $this->dryRunVideoIds[$archiveVideo->id] = true;
            }

            return 0;
        }

        if ($existingMap) {
            DB::table('videos')
                ->where('id', $existingMap->new_video_id)
                ->update($payload + ['updated_at' => now()]);

            $this->stats['videos_updated']++;

            return (int) $existingMap->new_video_id;
        }

        $videoId = (int) DB::table('videos')->insertGetId($payload + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('arch_video_import_maps')->insert([
            'old_video_id' => $archiveVideo->id,
            'new_video_id' => $videoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->stats['videos_created']++;

        return $videoId;
    }

    private function attachVideoToArticle(int $articleId, int $videoId): void
    {
        $exists = DB::table('articleables')
            ->where('article_id', $articleId)
            ->where('articleable_type', Video::class)
            ->where('articleable_id', $videoId)
            ->exists();

        if ($exists) {
            $this->stats['relations_existing']++;
            return;
        }

        DB::table('articleables')->insert([
            'article_id' => $articleId,
            'articleable_type' => Video::class,
            'articleable_id' => $videoId,
        ]);

        $this->stats['relations_created']++;
    }

    private function makeUniqueSlug(string $title, int $oldArticleId, ?int $existingArticleId = null): string
    {
        $base = Str::limit(Str::slug($title), 180, '');
        if ($base === '') {
            $base = "article-{$oldArticleId}";
        }

        $slug = $base;
        $counter = 2;

        while ($this->slugExists($slug, $existingArticleId)) {
            $slug = "{$base}-{$oldArticleId}";

            if (!$this->slugExists($slug, $existingArticleId)) {
                break;
            }

            $slug = "{$base}-{$oldArticleId}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreArticleId = null): bool
    {
        $query = DB::table('articles')->where('slug', $slug);

        if ($ignoreArticleId !== null) {
            $query->where('id', '!=', $ignoreArticleId);
        }

        return $query->exists();
    }

    private function extractYoutubeEmbedUrl(string $html): ?string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $patterns = [
            '~src=["\'](?:https?:)?//(?:www\.)?youtube\.com/embed/([A-Za-z0-9_-]+)(?:[?&][^"\']*)?["\']~i',
            '~data-oembed=["\']https?://youtu\.be/([A-Za-z0-9_-]+)(?:[?&][^"\']*)?["\']~i',
            '~data-oembed=["\']https?://(?:www\.)?youtube\.com/watch\?v=([A-Za-z0-9_-]+)(?:[?&][^"\']*)?["\']~i',
            '~https?://(?:www\.)?youtube\.com/embed/([A-Za-z0-9_-]+)(?:[?&][^\s<"\']*)?~i',
            '~https?://youtu\.be/([A-Za-z0-9_-]+)(?:[?&][^\s<"\']*)?~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return "https://www.youtube.com/embed/{$matches[1]}";
            }
        }

        return null;
    }

    private function makePhotoInfo(?string $text, ?string $link): ?string
    {
        $text = trim((string) $text);
        $link = trim((string) $link);

        if ($text === '' && $link === '') {
            return null;
        }

        if ($link === '') {
            return e($text);
        }

        $label = $text !== '' ? $text : $link;

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            e($link),
            e($label)
        );
    }

    private function normalizeDate(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return now()->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return now()->format('Y-m-d H:i:s');
        }
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function resolveRegionId(object $archiveArticle): ?int
    {
        $regionId = $this->option('region-id');

        if ($regionId !== null && $regionId !== '') {
            return (int) $regionId;
        }

        if ($this->option('use-archive-region')) {
            return $archiveArticle->id_reg ? (int) $archiveArticle->id_reg : null;
        }

        return null;
    }

    private function limitString(string $value, int $limit = 255): string
    {
        return mb_substr($value, 0, $limit);
    }

    private function articleExists(int $articleId): bool
    {
        return DB::table('articles')->where('id', $articleId)->exists();
    }

    private function videoExists(int $videoId): bool
    {
        return DB::table('videos')->where('id', $videoId)->exists();
    }

    private function printStats(): void
    {
        $this->newLine();
        $this->table(['Metric', 'Value'], collect($this->stats)
            ->map(fn (int $value, string $key) => [$key, $value])
            ->values()
            ->all());
    }

    private function requiredTablesExist(): bool
    {
        return Schema::hasTable('articles')
            && Schema::hasTable('videos')
            && Schema::hasTable('articleables')
            && Schema::hasTable('arch_article_import_maps')
            && Schema::hasTable('arch_video_import_maps');
    }

    private function selectedArticlesCount($query): int
    {
        $count = (int) $query->count();
        $limit = $this->option('limit');

        if ($limit !== null && $limit !== '') {
            $count = min($count, (int) $limit);
        }

        return $count;
    }
}
