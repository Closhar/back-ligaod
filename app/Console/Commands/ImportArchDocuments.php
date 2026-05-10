<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportArchDocuments extends Command
{
    protected $signature = 'import:arch-documents
        {--commit : Write documents and article relations}
        {--limit= : Limit number of archive documents}
        {--document-id=* : Import only selected archive document IDs}
        {--article-id=* : Import only documents linked to selected archive article IDs}
        {--stop-on-error : Stop import on first failed document}';

    protected $description = 'Import archive documents from arch.spbg_docs and S3 docs folder into current documents.';

    private bool $commit = false;

    private string $archiveConnection;

    private array $stats = [
        'documents_seen' => 0,
        'documents_created' => 0,
        'documents_existing' => 0,
        'files_missing' => 0,
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
            ? 'WRITE MODE: документы и связи будут записаны в текущую базу.'
            : 'DRY RUN: данные не будут записаны. Для записи добавь --commit.'
        );

        if (!$this->requiredTablesExist()) {
            $this->error('Не найдены нужные таблицы. Выполни миграции и проверь arch_article_import_maps.');

            return self::FAILURE;
        }

        $query = $this->archiveDocumentsQuery();
        $this->info('Archive documents selected: ' . (int) (clone $query)->count());

        $query->orderBy('id')->chunk(100, function ($documents) {
            foreach ($documents as $archiveDocument) {
                $this->stats['documents_seen']++;

                try {
                    $this->processDocument($archiveDocument);
                } catch (Throwable $exception) {
                    $this->stats['errors']++;
                    $this->error("Document {$archiveDocument->id}: {$exception->getMessage()}");

                    if ($this->option('stop-on-error')) {
                        throw $exception;
                    }
                }
            }
        });

        $this->printStats();

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function archiveDocumentsQuery()
    {
        $query = DB::connection($this->archiveConnection)
            ->table('spbg_docs')
            ->select([
                'id',
                'data',
                'name',
                'file_name',
                'id_doc_cat',
                'id_reg_fed',
                'id_comitet',
                'id_school',
                'tags',
            ])
            ->whereNotNull('file_name')
            ->where('file_name', '<>', '');

        $documentIds = array_filter(array_map('intval', (array) $this->option('document-id')));
        if ($documentIds !== []) {
            $query->whereIn('id', $documentIds);
        }

        $articleIds = array_filter(array_map('intval', (array) $this->option('article-id')));
        if ($articleIds !== []) {
            $query->whereIn('id', function ($subQuery) use ($articleIds) {
                $subQuery->from('spbg_linked')
                    ->select('id_doc')
                    ->whereIn('id_article', $articleIds)
                    ->whereNotNull('id_doc')
                    ->where('id_doc', '>', 0);
            });
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $query->limit((int) $limit);
        }

        return $query;
    }

    private function processDocument(object $archiveDocument): void
    {
        $fileName = ltrim(trim((string) $archiveDocument->file_name), '/');
        $filePath = str_starts_with($fileName, 'docs/') ? $fileName : "docs/{$fileName}";
        $disk = Storage::disk('public');

        if (!$disk->exists($filePath)) {
            $this->stats['files_missing']++;
            $this->warn("File not found: {$filePath}");

            return;
        }

        $map = DB::table('arch_document_import_maps')
            ->where('old_document_id', $archiveDocument->id)
            ->first();

        if ($map) {
            $documentId = (int) $map->new_document_id;
            $this->stats['documents_existing']++;
        } else {
            $documentId = $this->createDocument($archiveDocument, $filePath);
        }

        if ($this->commit && $documentId > 0) {
            $this->upsertImportMap($archiveDocument, $documentId, $fileName, 'imported');
        }

        $this->attachDocumentToImportedArticles((int) $archiveDocument->id, $documentId);
    }

    private function createDocument(object $archiveDocument, string $filePath): int
    {
        if (!$this->commit) {
            $this->stats['documents_created']++;

            return 0;
        }

        $disk = Storage::disk('public');
        $title = trim((string) $archiveDocument->name) ?: pathinfo($filePath, PATHINFO_FILENAME);

        $document = Document::create([
            'title' => mb_substr($title, 0, 255),
            'file_path' => $filePath,
            'original_name' => basename($filePath),
            'mime_type' => $disk->mimeType($filePath) ?: null,
            'size' => $disk->size($filePath) ?: null,
            'in_about' => false,
            'sort_order' => 500,
        ]);

        $this->stats['documents_created']++;

        return (int) $document->id;
    }

    private function attachDocumentToImportedArticles(int $oldDocumentId, int $documentId): void
    {
        $links = DB::connection($this->archiveConnection)
            ->table('spbg_linked')
            ->where('id_doc', $oldDocumentId)
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

            if (!$this->commit || $documentId <= 0) {
                $this->stats['article_relations_created']++;
                continue;
            }

            $exists = DB::table('articleables')
                ->where('article_id', $newArticleId)
                ->where('articleable_type', Document::class)
                ->where('articleable_id', $documentId)
                ->exists();

            if ($exists) {
                $this->stats['article_relations_existing']++;
                continue;
            }

            DB::table('articleables')->insert([
                'article_id' => $newArticleId,
                'articleable_type' => Document::class,
                'articleable_id' => $documentId,
            ]);

            $this->stats['article_relations_created']++;
        }
    }

    private function upsertImportMap(object $archiveDocument, int $documentId, string $fileName, string $status): void
    {
        DB::table('arch_document_import_maps')->updateOrInsert(
            ['old_document_id' => $archiveDocument->id],
            [
                'new_document_id' => $documentId,
                'file_name' => $fileName,
                'status' => $status,
                'errors' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function requiredTablesExist(): bool
    {
        return Schema::hasTable('documents')
            && Schema::hasTable('articleables')
            && Schema::hasTable('arch_article_import_maps')
            && Schema::hasTable('arch_document_import_maps');
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
