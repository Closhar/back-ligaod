<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    private $models = [
        'gpt-3.5-turbo' => [
            'max_tokens' => 2000,
            'temperature' => 0.5,
            'cost_per_1k_input' => 0.002,
            'cost_per_1k_output' => 0.002,
            'system_prompt' => 'Вы - опытный спортивный журналист. Пишите статьи в следующем формате:

1. Заголовок: краткий, информативный, с ключевым событием

2. Вступление (2-3 предложения): основная информация о матче, важные цифры

3. Подзаголовок "Первый тайм": хронология событий, ключевые моменты

4. Подзаголовок "Второй тайм": развитие событий, голы, важные эпизоды

5. Подзаголовок "Итоги": статистика, награды, важные факты

Правила написания:
- Используйте короткие предложения (до 15 слов)
- Пишите конкретно, избегайте общих фраз
- Каждый абзац - 2-3 предложения
- Используйте спортивную терминологию
- Добавляйте точные цифры и факты
- Избегайте клише и штампов
- Пишите в активном залоге
- Используйте прямую речь для цитат

ВАЖНО: После каждого подзаголовка делайте пустую строку. После каждого абзаца делайте пустую строку. Используйте переносы строк для структурирования текста.'
        ],
        'gpt-4-turbo-preview' => [
            'max_tokens' => 4000,
            'temperature' => 0.5,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
            'system_prompt' => 'Вы - ведущий спортивный аналитик. Создавайте глубокие аналитические статьи по следующей структуре:

1. Заголовок: отражает главное событие и его значимость

2. Вступление (3-4 предложения):
   - Контекст матча
   - Важные цифры и факты
   - Предыстория противостояния

3. Подзаголовок "Первый тайм":
   - Хронология ключевых моментов
   - Тактические особенности
   - Статистика и анализ

4. Подзаголовок "Второй тайм":
   - Развитие событий
   - Голы и важные эпизоды
   - Тактические изменения

5. Подзаголовок "Анализ":
   - Ключевые факторы победы/поражения
   - Выступления ключевых игроков
   - Тактические решения тренеров

6. Подзаголовок "Итоги":
   - Статистика матча
   - Награды и признания
   - Значимость результата

Правила написания:
- Используйте профессиональную спортивную терминологию
- Подкрепляйте анализ конкретными фактами и цифрами
- Структурируйте информацию логически
- Пишите короткими, четкими предложениями (до 15 слов)
- Избегайте общих фраз и клише
- Используйте активный залог
- Добавляйте экспертные оценки
- Включайте релевантную статистику
- Цитируйте ключевых участников
- Следите за балансом между аналитикой и повествованием

ВАЖНО: После каждого подзаголовка делайте пустую строку. После каждого абзаца делайте пустую строку. Используйте переносы строк для структурирования текста.'
        ]
    ];

    private function getModelConfig($model = null)
    {
        $model = $model ?? config('services.openai.default_model', 'gpt-3.5-turbo');
        return $this->models[$model] ?? $this->models['gpt-3.5-turbo'];
    }

    private function estimateCost($inputTokens, $outputTokens, $model)
    {
        $config = $this->getModelConfig($model);
        $inputCost = ($inputTokens / 1000) * $config['cost_per_1k_input'];
        $outputCost = ($outputTokens / 1000) * $config['cost_per_1k_output'];
        return $inputCost + $outputCost;
    }

    private function getCachedResponse($cacheKey)
    {
        return \Cache::get($cacheKey);
    }

    private function setCachedResponse($cacheKey, $response, $ttl = 3600)
    {
        \Cache::put($cacheKey, $response, $ttl);
    }

    private function postProcessText($text) {
        // Разбиваем текст на предложения
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Обрабатываем каждое предложение
        $processedSentences = array_map(function($sentence) {
            // Удаляем лишние пробелы
            $sentence = trim($sentence);

            // Проверяем длину предложения
            if (mb_strlen($sentence) > 100) {
                // Разбиваем длинное предложение на более короткие
                $parts = preg_split('/(?<=[,;])\s+/', $sentence);
                return implode(' ', $parts);
            }

            return $sentence;
        }, $sentences);

        // Собираем текст обратно
        $processedText = implode(' ', $processedSentences);

        // Удаляем множественные пробелы
        $processedText = preg_replace('/\s+/', ' ', $processedText);

        // Форматируем заголовки
        $processedText = preg_replace('/^([^:]+):/m', "\n$1:", $processedText);

        // Добавляем переносы строк после заголовков
        $processedText = preg_replace('/^([^:]+):\s*/m', "$1:\n\n", $processedText);

        // Добавляем переносы строк после абзацев
        $processedText = preg_replace('/\.\s+/', ".\n\n", $processedText);

        // Удаляем лишние переносы строк
        $processedText = preg_replace('/\n{3,}/', "\n\n", $processedText);

        // Форматируем списки
        $processedText = preg_replace('/^\s*-\s*/m', "\n- ", $processedText);

        // Добавляем отступы для абзацев
        $processedText = preg_replace('/\n\n([^\n])/m', "\n\n    $1", $processedText);

        // Удаляем лишние пробелы в начале и конце
        $processedText = trim($processedText);

        // Форматируем цитаты
        $processedText = preg_replace('/"([^"]+)"/', '«$1»', $processedText);

        // Форматируем тире
        $processedText = preg_replace('/\s*-\s*/', ' — ', $processedText);

        // Форматируем скобки
        $processedText = preg_replace('/\s*\(\s*/', ' (', $processedText);
        $processedText = preg_replace('/\s*\)\s*/', ') ', $processedText);

        // Форматируем двоеточие
        $processedText = preg_replace('/\s*:\s*/', ': ', $processedText);

        // Форматируем запятую
        $processedText = preg_replace('/\s*,\s*/', ', ', $processedText);

        // Форматируем точку
        $processedText = preg_replace('/\s*\.\s*/', '. ', $processedText);

        // Форматируем восклицательный знак
        $processedText = preg_replace('/\s*!\s*/', '! ', $processedText);

        // Форматируем вопросительный знак
        $processedText = preg_replace('/\s*\?\s*/', '? ', $processedText);

        // Удаляем лишние пробелы перед знаками препинания
        $processedText = preg_replace('/\s+([.,!?:;])/', '$1', $processedText);

        // Удаляем лишние пробелы после знаков препинания
        $processedText = preg_replace('/([.,!?:;])\s+/', '$1 ', $processedText);

        // Форматируем цифры и единицы измерения
        $processedText = preg_replace('/(\d+)\s*%/', '$1%', $processedText);
        $processedText = preg_replace('/(\d+)\s*:/', '$1:', $processedText);

        // Форматируем названия команд
        $processedText = preg_replace('/"([^"]+)"\s*\(([^)]+)\)/', '«$1» ($2)', $processedText);

        return $processedText;
    }

    private function fetchWebContent($url) {
        try {
            $response = Http::timeout(30)->get($url);
            if ($response->successful()) {
                $html = $response->body();

                // Удаляем скрипты и стили
                $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
                $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

                // Извлекаем основной контент
                $dom = new \DOMDocument();
                @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                $xpath = new \DOMXPath($dom);

                // Ищем основной контент (можно настроить под конкретные сайты)
                $content = '';

                // Пробуем найти основной контент по разным селекторам
                $selectors = [
                    '//article',
                    '//div[contains(@class, "article")]',
                    '//div[contains(@class, "content")]',
                    '//div[contains(@class, "post")]',
                    '//main'
                ];

                foreach ($selectors as $selector) {
                    $nodes = $xpath->query($selector);
                    if ($nodes->length > 0) {
                        foreach ($nodes as $node) {
                            $content .= $node->textContent . "\n";
                        }
                        break;
                    }
                }

                // Если контент не найден, берем body
                if (empty($content)) {
                    $body = $xpath->query('//body');
                    if ($body->length > 0) {
                        $content = $body->item(0)->textContent;
                    }
                }

                // Очищаем текст
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);

                return $content;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching web content: ' . $e->getMessage());
        }

        return null;
    }

    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:4000',
            'file_id' => 'nullable|string',
            'model' => 'nullable|string|in:gpt-3.5-turbo,gpt-4-turbo-preview',
            'use_cache' => 'nullable|boolean',
            'min_sentence_length' => 'nullable|integer|min:10|max:100',
            'max_sentence_length' => 'nullable|integer|min:20|max:200',
            'min_paragraph_length' => 'nullable|integer|min:1|max:5',
            'max_paragraph_length' => 'nullable|integer|min:2|max:10',
            'format' => 'nullable|string|in:plain,html,markdown',
            'url' => 'nullable|url'
        ]);

        try {
            $fullPrompt = $request->input('prompt');
            $model = $request->input('model');
            $useCache = $request->input('use_cache', true);
            $format = $request->input('format', 'plain');
            $url = $request->input('url');
            $config = $this->getModelConfig($model);

            // Если передан URL, получаем контент с веб-страницы
            if ($url) {
                $webContent = $this->fetchWebContent($url);
                if ($webContent) {
                    $fullPrompt .= "\n\nИспользуйте следующий контент как источник информации:\n\n" . $webContent;
                }
            }

            // Добавляем в промпт требования к форматированию
            if ($format === 'html') {
                $fullPrompt .= "\n\nПожалуйста, форматируйте текст с использованием HTML-тегов для заголовков, абзацев и списков.";
            } elseif ($format === 'markdown') {
                $fullPrompt .= "\n\nПожалуйста, форматируйте текст с использованием Markdown для заголовков, абзацев и списков.";
            }

            // Проверяем длину промпта
            if (mb_strlen($fullPrompt) > 4000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Превышен лимит длины промпта (4000 символов)',
                    'limits' => [
                        'max_prompt_length' => 4000,
                        'current_prompt_length' => mb_strlen($fullPrompt)
                    ]
                ], 400);
            }

            // Если есть file_id, добавляем содержимое файла к промту
            if ($request->has('file_id')) {
                $filePath = storage_path('app/public/ai_files/' . $request->file_id);
                if (file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);
                    $fullPrompt .= "\n\nСодержимое файла:\n" . $fileContent;
                }
            }

            // Проверяем кэш
            if ($useCache) {
                $cacheKey = 'ai_response_' . md5($fullPrompt . $model);
                $cachedResponse = $this->getCachedResponse($cacheKey);
                if ($cachedResponse) {
                    return response()->json([
                        'success' => true,
                        'data' => $cachedResponse,
                        'source' => 'cache',
                        'limits' => [
                            'max_prompt_length' => 4000,
                            'max_tokens' => $config['max_tokens'],
                            'model' => $model ?? config('services.openai.default_model', 'gpt-3.5-turbo')
                        ]
                    ]);
                }
            }

            // Отправляем запрос к AI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key')
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model ?? config('services.openai.default_model', 'gpt-3.5-turbo'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $config['system_prompt']
                    ],
                    [
                        'role' => 'user',
                        'content' => $fullPrompt
                    ]
                ],
                'temperature' => 0.5,
                'max_tokens' => $config['max_tokens'],
                'presence_penalty' => 0.3,
                'frequency_penalty' => 0.3,
                'top_p' => 0.8,
                'response_format' => ['type' => 'text']
            ]);

            if (!$response->successful()) {
                throw new \Exception('Ошибка при обращении к AI API');
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'];

            // Применяем постобработку текста
            $content = $this->postProcessText($content);

            // Конвертируем в нужный формат
            if ($format === 'html') {
                $content = $this->convertToHtml($content);
            } elseif ($format === 'markdown') {
                $content = $this->convertToMarkdown($content);
            }

            // Кэшируем результат
            if ($useCache) {
                $this->setCachedResponse($cacheKey, $content);
            }

            // Оцениваем стоимость
            $inputTokens = $responseData['usage']['prompt_tokens'];
            $outputTokens = $responseData['usage']['completion_tokens'];
            $estimatedCost = $this->estimateCost($inputTokens, $outputTokens, $model);

            return response()->json([
                'success' => true,
                'data' => $content,
                'source' => 'api',
                'usage' => [
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'estimated_cost' => $estimatedCost,
                    'cost_breakdown' => [
                        'input_cost' => ($inputTokens / 1000) * $config['cost_per_1k_input'],
                        'output_cost' => ($outputTokens / 1000) * $config['cost_per_1k_output']
                    ]
                ],
                'limits' => [
                    'max_prompt_length' => 4000,
                    'max_tokens' => $config['max_tokens'],
                    'model' => $model ?? config('services.openai.default_model', 'gpt-3.5-turbo'),
                    'temperature' => 0.5
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AI Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function convertToHtml($text) {
        // Конвертируем заголовки
        $text = preg_replace('/^([^:]+):/m', '<h3>$1</h3>', $text);

        // Конвертируем абзацы
        $text = '<p>' . str_replace("\n\n", '</p><p>', $text) . '</p>';

        // Конвертируем списки
        $text = preg_replace('/<p>-\s*(.*?)<\/p>/m', '<li>$1</li>', $text);
        $text = preg_replace('/<li>.*?<\/li>/s', '<ul>$0</ul>', $text);

        // Конвертируем цитаты
        $text = preg_replace('/«(.*?)»/', '<q>$1</q>', $text);

        return $text;
    }

    private function convertToMarkdown($text) {
        // Конвертируем заголовки
        $text = preg_replace('/^([^:]+):/m', '### $1', $text);

        // Конвертируем списки
        $text = preg_replace('/^-\s*(.*?)$/m', '- $1', $text);

        // Конвертируем цитаты
        $text = preg_replace('/«(.*?)»/', '> $1', $text);

        return $text;
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt|max:10240' // макс. 10MB
        ]);

        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('ai_files', $fileName, 'public');

            return response()->json([
                'success' => true,
                'data' => [
                    'file_id' => $fileName
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


}
