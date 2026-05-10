<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class NormalizeArchArticlePhotoTitles extends Command
{
    protected $signature = 'maintenance:normalize-arch-article-photo-titles
        {--commit : Apply title updates and delete empty non-photo articles}
        {--limit= : Limit processed archive articles}
        {--article-id=* : Process only selected archive article IDs}
        {--include-unlinked : Process all mapped archive articles, not only articles linked to galleries}
        {--stop-on-error : Stop on first failed article}';

    protected $description = 'Normalize imported article "(ФОТО)" suffix by linked archive gallery kind and article maps.';

    private bool $commit = false;

    private string $archiveConnection;

    private array $stats = [
        'archive_articles_seen' => 0,
        'missing_map' => 0,
        'missing_new_article' => 0,
        'photo_titles_already_ok' => 0,
        'photo_titles_updated' => 0,
        'non_photo_titles_already_ok' => 0,
        'non_photo_titles_updated' => 0,
        'non_photo_empty_deleted' => 0,
        'non_photo_empty_would_delete' => 0,
        'errors' => 0,
    ];

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');
        $this->archiveConnection = config('database.archive_connection', 'arch_mysql');

        $this->warn($this->commit
            ? 'WRITE MODE: заголовки будут изменены, пустые не-фото новости будут удалены.'
            : 'DRY RUN: данные не будут изменены. Для применения добавь --commit.'
        );

        if (! $this->requiredTablesExist()) {
            $this->error('Не найдены нужные таблицы: articles, arch_article_import_maps, arch.spbg_articles, arch.spbg_linked или arch.spbg_glr.');

            return self::FAILURE;
        }

        $query = $this->archiveArticlesQuery();
        $this->info('Archive articles selected: '.$this->selectedArchiveArticlesCount());

        $query->orderBy('spbg_articles.id')->chunk(200, function ($archiveArticles) {
            foreach ($archiveArticles as $archiveArticle) {
                $this->stats['archive_articles_seen']++;

                try {
                    $this->processArchiveArticle($archiveArticle);
                } catch (Throwable $exception) {
                    $this->stats['errors']++;
                    $this->error("Article {$archiveArticle->id}: {$exception->getMessage()}");

                    if ($this->option('stop-on-error')) {
                        throw $exception;
                    }
                }
            }
        });

        $this->printStats();

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function archiveArticlesQuery()
    {
        $query = DB::connection($this->archiveConnection)
            ->table('spbg_articles')
            ->join('spbg_linked', 'spbg_linked.id_article', '=', 'spbg_articles.id')
            ->join('spbg_glr', 'spbg_glr.id', '=', 'spbg_linked.id_foto')
            ->whereNotNull('spbg_linked.id_foto')
            ->where('spbg_linked.id_foto', '>', 0)
            ->select([
                'spbg_articles.id',
                DB::raw("MAX(CASE WHEN spbg_glr.kind = 'nano_photos_provider2' THEN 1 ELSE 0 END) as has_photo_gallery"),
                DB::raw("GROUP_CONCAT(DISTINCT spbg_glr.kind ORDER BY spbg_glr.kind SEPARATOR ',') as gallery_kinds"),
            ])
            ->groupBy('spbg_articles.id');

        if ($this->option('include-unlinked')) {
            $this->warn('--include-unlinked проигнорирован: фактическая таблица spbg_articles не содержит kind, поэтому признак берется из привязанной spbg_glr.kind.');
        }

        $articleIds = array_filter(array_map('intval', (array) $this->option('article-id')));
        if ($articleIds !== []) {
            $query->whereIn('spbg_articles.id', $articleIds);
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $query->limit((int) $limit);
        }

        return $query;
    }

    private function selectedArchiveArticlesCount(): int
    {
        $query = DB::connection($this->archiveConnection)
            ->table('spbg_articles')
            ->join('spbg_linked', 'spbg_linked.id_article', '=', 'spbg_articles.id')
            ->join('spbg_glr', 'spbg_glr.id', '=', 'spbg_linked.id_foto')
            ->whereNotNull('spbg_linked.id_foto')
            ->where('spbg_linked.id_foto', '>', 0);

        $articleIds = array_filter(array_map('intval', (array) $this->option('article-id')));
        if ($articleIds !== []) {
            $query->whereIn('spbg_articles.id', $articleIds);
        }

        return (int) $query->distinct()->count('spbg_articles.id');
    }

    private function processArchiveArticle(object $archiveArticle): void
    {
        $newArticleId = DB::table('arch_article_import_maps')
            ->where('old_article_id', $archiveArticle->id)
            ->value('new_article_id');

        if (! $newArticleId) {
            $this->stats['missing_map']++;

            return;
        }

        $article = DB::table('articles')
            ->where('id', $newArticleId)
            ->first(['id', 'title', 'content', 'image']);

        if (! $article) {
            $this->stats['missing_new_article']++;

            return;
        }

        $title = trim((string) $article->title);

        if ((int) $archiveArticle->has_photo_gallery === 1) {
            $this->processPhotoArticle((int) $article->id, $title);

            return;
        }

        $this->processNonPhotoArticle($article, $title);
    }

    private function processPhotoArticle(int $articleId, string $title): void
    {
        if ($this->hasPhotoSuffix($title)) {
            $this->stats['photo_titles_already_ok']++;

            return;
        }

        $newTitle = trim($title).' (ФОТО)';

        if ($this->commit) {
            DB::table('articles')
                ->where('id', $articleId)
                ->update([
                    'title' => $newTitle,
                    'updated_at' => now(),
                ]);
        }

        $this->stats['photo_titles_updated']++;
        $this->line("PHOTO title: #{$articleId} {$title} -> {$newTitle}");
    }

    private function processNonPhotoArticle(object $article, string $title): void
    {
        if ($this->isBlankHtml((string) $article->content)) {
            if ($this->commit) {
                $this->deleteArticle($article);
                $this->stats['non_photo_empty_deleted']++;
            } else {
                $this->stats['non_photo_empty_would_delete']++;
            }

            $this->line("DELETE empty non-photo article: #{$article->id} {$title}");

            return;
        }

        if (! $this->hasPhotoSuffix($title)) {
            $this->stats['non_photo_titles_already_ok']++;

            return;
        }

        $newTitle = $this->removePhotoSuffix($title);

        if ($this->commit) {
            DB::table('articles')
                ->where('id', $article->id)
                ->update([
                    'title' => $newTitle,
                    'updated_at' => now(),
                ]);
        }

        $this->stats['non_photo_titles_updated']++;
        $this->line("NON-PHOTO title: #{$article->id} {$title} -> {$newTitle}");
    }

    private function deleteArticle(object $article): void
    {
        DB::transaction(function () use ($article) {
            if (! empty($article->image)) {
                Storage::disk('public')->delete($article->image);
            }

            DB::table('articleables')->where('article_id', $article->id)->delete();

            if (Schema::hasTable('article_article_tag')) {
                DB::table('article_article_tag')->where('article_id', $article->id)->delete();
            }

            if (Schema::hasTable('article_views')) {
                DB::table('article_views')->where('article_id', $article->id)->delete();
            }

            DB::table('articles')->where('id', $article->id)->delete();
        });
    }

    private function hasPhotoSuffix(string $title): bool
    {
        return (bool) preg_match('/\s*\(ФОТО\)\s*$/ui', $title);
    }

    private function removePhotoSuffix(string $title): string
    {
        return trim((string) preg_replace('/\s*\(ФОТО\)\s*$/ui', '', $title));
    }

    private function isBlankHtml(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);

        return trim($text) === '';
    }

    private function requiredTablesExist(): bool
    {
        return Schema::hasTable('articles')
            && Schema::hasTable('articleables')
            && Schema::hasTable('arch_article_import_maps')
            && Schema::connection($this->archiveConnection)->hasTable('spbg_articles')
            && Schema::connection($this->archiveConnection)->hasTable('spbg_linked')
            && Schema::connection($this->archiveConnection)->hasTable('spbg_glr');
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
