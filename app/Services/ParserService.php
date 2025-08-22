<?php

namespace App\Services;

use App\Models\ParserTemplate;
use App\Models\ParserLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ParserService
{
    public function parseUrl(ParserTemplate $template, string $url): ParserLog
    {
        try {
            // Получаем HTML страницы
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                throw new \Exception("HTTP Error: " . $response->status());
            }

            $html = $response->body();

            // Проверяем условия парсинга
            if (!$template->shouldParse($html)) {
                throw new \Exception("Page does not meet parsing conditions");
            }

            // Парсим данные
            $parsedData = $this->parseHtml($template, $html);

            // Сохраняем в БД
            $result = $this->saveParsedData($template, $parsedData);

            // Создаем лог
            return ParserLog::create([
                'parser_template_id' => $template->id,
                'url' => $url,
                'raw_data' => $html,
                'parsed_data' => $parsedData,
                'status' => 'success',
                'records_created' => $result['created'] ?? 0,
                'records_updated' => $result['updated'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Parser error: ' . $e->getMessage(), [
                'template_id' => $template->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return ParserLog::create([
                'parser_template_id' => $template->id,
                'url' => $url,
                'raw_data' => $html ?? null,
                'parsed_data' => null,
                'errors' => ['message' => $e->getMessage()],
                'status' => 'error',
                'records_created' => 0,
                'records_updated' => 0,
            ]);
        }
    }

    private function parseHtml(ParserTemplate $template, string $html): array
    {
        $data = [];

        foreach ($template->fields as $field) {
            $value = $field->extractValue($html);

            if ($value !== null) {
                $data[$field->name] = [
                    'value' => $value,
                    'field_id' => $field->id,
                    'target_table' => $field->target_table,
                    'target_field' => $field->target_field,
                    'update_strategy' => $field->update_strategy,
                ];
            }
        }

        return $data;
    }

    private function saveParsedData(ParserTemplate $template, array $data): array
    {
        $created = 0;
        $updated = 0;

        foreach ($data as $fieldData) {
            if (empty($fieldData['target_table']) || empty($fieldData['target_field'])) {
                continue;
            }

            try {
                $result = $this->saveToDatabase(
                    $fieldData['target_table'],
                    $fieldData['target_field'],
                    $fieldData['value'],
                    $fieldData['update_strategy']
                );

                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to save parsed data: ' . $e->getMessage(), [
                    'table' => $fieldData['target_table'],
                    'field' => $fieldData['target_field'],
                    'value' => $fieldData['value'],
                ]);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function saveToDatabase(string $table, string $field, mixed $value, string $strategy): string
    {
        // Здесь должна быть логика сохранения в конкретную таблицу
        // Это упрощенная версия, в реальном проекте нужно реализовать
        // специфичную логику для каждой таблицы

        switch ($strategy) {
            case 'insert':
                // Логика вставки
                return 'created';

            case 'update':
                // Логика обновления
                return 'updated';

            case 'upsert':
                // Логика upsert
                return 'updated';

            default:
                return 'skipped';
        }
    }

    public function testTemplate(ParserTemplate $template, string $url): array
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => "HTTP Error: " . $response->status(),
                    'data' => [],
                ];
            }

            $html = $response->body();

            if (!$template->shouldParse($html)) {
                return [
                    'success' => false,
                    'error' => "Page does not meet parsing conditions",
                    'data' => [],
                ];
            }

            $parsedData = $this->parseHtml($template, $html);

            return [
                'success' => true,
                'error' => null,
                'data' => $parsedData,
                'html_preview' => substr($html, 0, 1000) . '...',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }
}
