<?php

namespace App\Console\Commands;

use App\Models\ArticleTag;
use App\Models\Document;
use App\Models\Gallery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncArticleAttachmentTags extends Command
{
    protected $signature = 'maintenance:sync-article-attachment-tags
        {--commit : Create missing tags and article-tag relations}';

    protected $description = 'Synchronize Фото and Документ tags from actual article gallery/document relations.';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $this->warn($commit
            ? 'WRITE MODE: missing attachment tags and relations will be created.'
            : 'DRY RUN: data will not be changed. Use --commit to apply changes.'
        );

        $definitions = [
            'photo' => ['title' => 'Фото', 'articleable_type' => Gallery::class],
            'document' => ['title' => 'Документ', 'articleable_type' => Document::class],
        ];

        $rows = [];

        foreach ($definitions as $slug => $definition) {
            $tag = ArticleTag::query()->where('slug', $slug)->first();
            $attachmentArticleIds = DB::table('articleables')
                ->where('articleable_type', $definition['articleable_type'])
                ->distinct()
                ->pluck('article_id');

            $existingRelations = $tag === null
                ? collect()
                : DB::table('article_article_tag')
                    ->where('article_tag_id', $tag->id)
                    ->whereIn('article_id', $attachmentArticleIds)
                    ->pluck('article_id');

            $missingArticleIds = $attachmentArticleIds->diff($existingRelations)->values();

            $rows[] = [
                'slug' => $slug,
                'title' => $definition['title'],
                'tag_exists' => $tag !== null,
                'attachment_articles' => $attachmentArticleIds->count(),
                'missing_relations' => $missingArticleIds->count(),
                'tag' => $tag,
                'missing_article_ids' => $missingArticleIds,
            ];
        }

        $this->table(
            ['Tag', 'Exists', 'Articles with attachment', 'Missing relations'],
            array_map(fn (array $row) => [
                $row['title'],
                $row['tag_exists'] ? 'yes' : 'no',
                $row['attachment_articles'],
                $row['missing_relations'],
            ], $rows)
        );

        if (!$commit) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $tag = $row['tag'] ?? ArticleTag::query()->firstOrCreate(
                    ['slug' => $row['slug']],
                    ['title' => $row['title']]
                );

                $now = now();
                $payload = $row['missing_article_ids']
                    ->map(fn (int $articleId) => [
                        'article_id' => $articleId,
                        'article_tag_id' => $tag->id,
                    ])
                    ->all();

                if ($payload !== []) {
                    DB::table('article_article_tag')->insertOrIgnore($payload);
                }
            }
        });

        $this->info('Synchronization complete.');

        return self::SUCCESS;
    }
}
