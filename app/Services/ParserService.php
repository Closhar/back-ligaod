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
            // Получаем HTML страницы с заголовками для обхода антибот защиты
            $headers = $this->getHeaders($template);
            $response = Http::timeout(30)->withHeaders($headers)->get($url);

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
            // Получаем HTML страницы с заголовками для обхода антибот защиты
            $headers = $this->getHeaders($template);
            $response = Http::timeout(30)->withHeaders($headers)->get($url);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => "HTTP Error: " . $response->status(),
                    'data' => [],
                ];
            }

            $html = $response->body();

            // Добавляем отладочную информацию
            $debugInfo = [];
            if (!empty($template->conditions)) {
                foreach ($template->conditions as $index => $condition) {
                    $value = $template->extractValue($html, $condition['selector'], $condition['type'] ?? 'css');
                    $debugInfo[] = [
                        'condition' => $condition,
                        'extracted_value' => $value,
                        'passed' => $this->checkCondition($condition, $value)
                    ];
                }
            }

            if (!$template->shouldParse($html)) {
                return [
                    'success' => false,
                    'error' => "Page does not meet parsing conditions",
                    'data' => [],
                    'debug_info' => $debugInfo,
                    'html_preview' => substr($html, 0, 1000) . '...',
                ];
            }

            $parsedData = $this->parseHtml($template, $html);

            return [
                'success' => true,
                'error' => null,
                'data' => $parsedData,
                'html_preview' => substr($html, 0, 1000) . '...',
                'debug_info' => $debugInfo,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Получает заголовки для HTTP запроса
     * Если в шаблоне есть пользовательские заголовки, использует их
     * Иначе использует стандартные заголовки для обхода антибот защиты
     */
    private function getHeaders(ParserTemplate $template): array
    {
        // Если в шаблоне есть пользовательские заголовки, используем их
        if (!empty($template->headers) && is_array($template->headers)) {
            return $template->headers;
        }

        // Стандартные заголовки для обхода антибот защиты
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Cache-Control' => 'max-age=0',
        ];
    }

    /**
     * Проверяет условие парсинга
     */
    private function checkCondition(array $condition, string $value): bool
    {
        if (isset($condition['required']) && $condition['required'] && empty($value)) {
            return false;
        }

        if (isset($condition['contains']) && !str_contains($value, $condition['contains'])) {
            return false;
        }

        if (isset($condition['equals']) && $value !== $condition['equals']) {
            return false;
        }

        return true;
    }
}
