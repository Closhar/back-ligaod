<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AIController extends Controller
{
    private $models = [
        'gpt-3.5-turbo' => [
            'max_tokens' => 2000,
            'temperature' => 0.5,
            'cost_per_1k_input' => 0.002,
            'cost_per_1k_output' => 0.002,
            'max_file_length' => 4000,
            'system_prompt' => 'Вы - опытный спортивный журналист. Ваша задача - создавать качественные тексты на основе предоставленной информации.

Правила работы с информацией:
- Строго следуйте инструкциям пользователя в его промпте
- Используйте только предоставленную информацию
- Сохраняйте точность в передаче результатов и фактов
- Используйте спортивную терминологию
- Адаптируйте стиль и формат под запрос пользователя

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте информацию, которой нет в исходных данных
- Сохраняйте логическую структуру текста'
        ],
        'gpt-4-turbo-preview' => [
            'max_tokens' => 4000,
            'temperature' => 0.5,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
            'max_file_length' => 8000,
            'system_prompt' => 'Вы - ведущий спортивный аналитик. Ваша задача - создавать качественные аналитические тексты на основе предоставленной информации.

Правила работы с информацией:
- Строго следуйте инструкциям пользователя в его промпте
- Используйте только предоставленную информацию
- Сохраняйте точность в передаче результатов и фактов
- Используйте профессиональную спортивную терминологию
- Добавляйте контекст и анализ, если информация позволяет
- Адаптируйте стиль и формат под запрос пользователя

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте информацию, которой нет в исходных данных
- Сохраняйте логическую структуру текста'
        ]
    ];

    private $fileSystemPrompts = [
        'gpt-3.5-turbo' => 'Вы - опытный спортивный журналист. Ваша задача - обработать информацию из приложенного файла и создать текст согласно запросу пользователя.

В файле содержится информация о спортивных событиях в следующем формате:
- Название события/матча
- Результат
- Место проведения (если указано)
- Описание события
- Дополнительная информация (если есть)

Правила работы с информацией:
- Строго следуйте инструкциям пользователя в его промпте
- Используйте только информацию из приложенного файла
- Сохраняйте точность в передаче результатов и фактов
- Используйте спортивную терминологию
- Адаптируйте стиль и формат под запрос пользователя

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте информацию, которой нет в файле
- Сохраняйте логическую структуру текста',
        'gpt-4-turbo-preview' => 'Вы - ведущий спортивный аналитик. Ваша задача - обработать информацию из приложенного файла и создать текст согласно запросу пользователя.

В файле содержится информация о спортивных событиях в следующем формате:
- Название события/матча
- Результат
- Место проведения (если указано)
- Описание события
- Дополнительная информация (если есть)

Правила работы с информацией:
- Строго следуйте инструкциям пользователя в его промпте
- Используйте только информацию из приложенного файла
- Сохраняйте точность в передаче результатов и фактов
- Используйте профессиональную спортивную терминологию
- Добавляйте контекст и анализ, если информация позволяет
- Адаптируйте стиль и формат под запрос пользователя

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте информацию, которой нет в файле
- Сохраняйте логическую структуру текста'
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

    private function decodeUnicode($text) {
        return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function($matches) {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
        }, $text);
    }

    private function postProcessText($text) {
        // Декодируем Unicode-последовательности
        return $this->decodeUnicode($text);
    }

    private function fetchWebContent($url, $maxLength = 2000) {
        try {
            \Log::info('Начало fetchWebContent', ['url' => $url, 'maxLength' => $maxLength]);

            // Проверяем кэш
            $cacheKey = 'web_content_' . md5($url);
            $cachedContent = \Cache::get($cacheKey);
            if ($cachedContent) {
                \Log::info('Найден кэшированный контент', ['url' => $url]);
                return $cachedContent;
            }

            // Проверяем URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \Exception('Некорректный URL: ' . $url);
            }

            \Log::info('Отправляем HTTP запрос', ['url' => $url]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                    'Cache-Control' => 'max-age=0'
                ])
                ->get($url);

            \Log::info('Получен HTTP ответ', [
                'status' => $response->status(),
                'headers' => $response->headers()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Ошибка HTTP запроса: ' . $response->status());
            }

            $html = $response->body();
            \Log::info('Получен HTML контент', ['length' => strlen($html)]);

            if (empty($html)) {
                throw new \Exception('Получен пустой ответ от сервера');
            }

            // Удаляем скрипты, стили, комментарии и рекламу
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
            $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
            $html = preg_replace('/<!--(.*?)-->/s', '', $html);
            $html = preg_replace('/<div[^>]*class="[^"]*ad[^"]*"[^>]*>.*?<\/div>/is', '', $html);
            $html = preg_replace('/<div[^>]*id="[^"]*ad[^"]*"[^>]*>.*?<\/div>/is', '', $html);

            // Извлекаем основной контент
            $dom = new \DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new \DOMXPath($dom);

            // Ищем основной контент статьи
            $content = '';
            $articleFound = false;

            // Сначала ищем заголовок
            $titleSelectors = [
                '//h1',
                '//meta[@property="og:title"]/@content',
                '//title'
            ];

            foreach ($titleSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $content .= "Заголовок: " . trim($nodes->item(0)->textContent) . "\n\n";
                    break;
                }
            }

            // Ищем основной контент статьи
            $articleSelectors = [
                '//article',
                '//div[contains(@class, "article")]',
                '//div[contains(@class, "post")]',
                '//div[contains(@class, "content")]',
                '//main'
            ];

            foreach ($articleSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $articleFound = true;
                    foreach ($nodes as $node) {
                        // Извлекаем только текст из параграфов и заголовков
                        $elements = $xpath->query('.//p | .//h2 | .//h3 | .//h4', $node);
                        foreach ($elements as $element) {
                            $text = trim($element->textContent);
                            if (!empty($text)) {
                                // Определяем тип элемента
                                $tagName = $element->nodeName;
                                if (in_array($tagName, ['h2', 'h3', 'h4'])) {
                                    $content .= "\n" . $text . "\n\n";
                                } else {
                                    $content .= $text . "\n\n";
                                }
                            }
                        }
                    }
                    break;
                }
            }

            // Если статья не найдена, ищем в body
            if (!$articleFound) {
                $body = $xpath->query('//body');
                if ($body->length > 0) {
                    $elements = $xpath->query('.//p | .//h2 | .//h3 | .//h4', $body->item(0));
                    foreach ($elements as $element) {
                        $text = trim($element->textContent);
                        if (!empty($text)) {
                            $tagName = $element->nodeName;
                            if (in_array($tagName, ['h2', 'h3', 'h4'])) {
                                $content .= "\n" . $text . "\n\n";
                            } else {
                                $content .= $text . "\n\n";
                            }
                        }
                    }
                }
            }

            // Очищаем текст
            $content = preg_replace('/\s+/', ' ', $content);
            $content = preg_replace('/\n\s*\n/', "\n\n", $content);
            $content = trim($content);

            if (empty($content)) {
                throw new \Exception('Не удалось извлечь контент из HTML');
            }

            // Если текст слишком длинный, берем только начало
            if (mb_strlen($content) > $maxLength) {
                // Находим последний полный абзац в пределах лимита
                $truncated = mb_substr($content, 0, $maxLength);
                $lastParagraph = mb_strrpos($truncated, "\n\n");
                if ($lastParagraph !== false) {
                    $content = mb_substr($content, 0, $lastParagraph) . "\n\n...";
                } else {
                    $content = $truncated . "...";
                }
            }

            \Log::info('Успешно извлечен контент', ['length' => mb_strlen($content)]);

            // Кэшируем результат на 1 час
            \Cache::put($cacheKey, $content, 3600);

            return $content;
        } catch (\Exception $e) {
            \Log::error('Ошибка в fetchWebContent', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Ошибка при получении контента с сайта: ' . $e->getMessage());
        }
    }

    private function getSystemPrompt($model, $hasFile = false)
    {
        if ($hasFile) {
            return $this->fileSystemPrompts[$model] ?? $this->fileSystemPrompts['gpt-3.5-turbo'];
        }
        return $this->models[$model]['system_prompt'] ?? $this->models['gpt-3.5-turbo']['system_prompt'];
    }

    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:4000',
            'file_id' => 'nullable|string',
            'model' => 'nullable|string|in:gpt-3.5-turbo,gpt-4-turbo-preview',
            'use_cache' => 'nullable|boolean',
            'format' => 'nullable|string|in:plain,html,markdown',
            'url' => 'nullable|url',
            'max_content_length' => 'nullable|integer|min:500|max:5000',
            'max_file_length' => 'nullable|integer|min:1000|max:10000'
        ]);

        try {
            $fullPrompt = $request->input('prompt');
            $model = $request->input('model');
            $useCache = $request->input('use_cache', true);
            $format = $request->input('format', 'plain');
            $url = $request->input('url');
            $maxContentLength = $request->input('max_content_length', 2000);
            $config = $this->getModelConfig($model);
            $maxFileLength = $request->input('max_file_length', $config['max_file_length']);
            $hasFile = false;

            // Если есть file_id, добавляем содержимое файла к промту
            if ($request->has('file_id')) {
                $filePath = storage_path('app/public/ai_files/' . $request->file_id);
                if (file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);
                    if ($fileContent) {
                        $hasFile = true;
                        // Обрезаем содержимое файла если оно превышает лимит для выбранной модели
                        if (mb_strlen($fileContent) > $maxFileLength) {
                            $fileContent = mb_substr($fileContent, 0, $maxFileLength) . "\n\n... (текст обрезан до " . $maxFileLength . " символов для модели " . $model . ")";
                        }
                        $fullPrompt .= "\n\nСодержимое файла для анализа:\n\n" . $fileContent;
                    }
                }
            }

            // Если передан URL, получаем контент с веб-страницы
            if ($url) {
                \Log::info('Начинаем получение контента с URL', ['url' => $url]);
                try {
                    $webContent = $this->fetchWebContent($url, $maxContentLength);
                    \Log::info('Получен контент с URL', ['content_length' => mb_strlen($webContent ?? '')]);

                    if ($webContent) {
                        // Проверяем, не превысит ли добавление контента лимит промпта
                        $promptWithContent = $fullPrompt . "\n\nИспользуйте следующий контент как источник информации:\n\n" . $webContent;
                        if (mb_strlen($promptWithContent) > 4000) {
                            // Если превышает, сокращаем контент
                            $availableLength = 4000 - mb_strlen($fullPrompt) - 50; // 50 символов на служебный текст
                            $webContent = $this->fetchWebContent($url, $availableLength);
                            $promptWithContent = $fullPrompt . "\n\nИспользуйте следующий контент как источник информации:\n\n" . $webContent;
                        }
                        $fullPrompt = $promptWithContent;
                    } else {
                        \Log::error('Не удалось получить контент с URL', ['url' => $url]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Не удалось получить контент с указанного URL'
                        ], 400);
                    }
                } catch (\Exception $e) {
                    \Log::error('Ошибка при получении контента с URL', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Ошибка при получении контента с URL: ' . $e->getMessage()
                    ], 500);
                }
            }

            // Проверяем длину промпта только если нет файла
            if (!$hasFile && mb_strlen($fullPrompt) > 4000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Превышен лимит длины промпта (4000 символов)',
                    'limits' => [
                        'max_prompt_length' => 4000,
                        'current_prompt_length' => mb_strlen($fullPrompt)
                    ]
                ], 400);
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
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('services.openai.api_key')
                ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model ?? config('services.openai.default_model', 'gpt-3.5-turbo'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt($model, $hasFile)
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
                    throw new \Exception('Ошибка при обращении к AI API: ' . $response->body());
                }
            } catch (\Exception $e) {
                throw new \Exception('Ошибка при обращении к AI API: ' . $e->getMessage());
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
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function convertToHtml($text) {
        // Разбиваем текст на абзацы
        $paragraphs = explode("\n\n", $text);
        $html = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;

            // Проверяем, является ли абзац заголовком события
            if (preg_match('/^([🏇⚾🏑⚽🏀🎾🏐🏈🏉🎱🏓🏸🏒🏑🏏🎯🎳⛳🏌️🏄🏊🤽🚣🏇🚴🚵🤸🏋️🤼🤾].*?)$/u', $paragraph, $matches)) {
                $html .= '<h3>' . $matches[1] . '</h3>';
            }
            // Проверяем, является ли абзац результатом или местом проведения
            else if (preg_match('/^(Результат:|Место проведения:)/', $paragraph)) {
                $html .= '<p>' . $paragraph . '</p>';
            }
            // Обычный абзац
            else {
                $html .= '<p>' . $paragraph . '</p>';
            }
        }

        return $html;
    }

    private function convertToMarkdown($text) {
        // Разбиваем текст на абзацы
        $paragraphs = explode("\n\n", $text);
        $markdown = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;

            // Проверяем, является ли абзац заголовком события
            if (preg_match('/^([🏇⚾🏑⚽🏀🎾🏐🏈🏉🎱🏓🏸🏒🏑🏏🎯🎳⛳🏌️🏄🏊🤽🚣🏇🚴🚵🤸🏋️🤼🤾].*?)$/u', $paragraph, $matches)) {
                $markdown .= "\n### " . $matches[1] . "\n\n";
            }
            // Проверяем, является ли абзац результатом или местом проведения
            else if (preg_match('/^(Результат:|Место проведения:)/', $paragraph)) {
                $markdown .= $paragraph . "\n\n";
            }
            // Обычный абзац
            else {
                $markdown .= $paragraph . "\n\n";
            }
        }

        return trim($markdown);
    }

    public function uploadFile(Request $request)
    {
        \Log::info('Upload file request', [
            'headers' => $request->headers->all()
        ]);

        $request->validate([
            'file' => 'required|file|mimes:txt|max:10240' // макс. 10MB
        ]);

        try {
            $file = $request->file('file');
            \Log::info('File received', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize()
            ]);

            $fileName = time() . '_' . $file->getClientOriginalName();
            $directory = storage_path('app/public/ai_files');

            \Log::info('Directory check', [
                'path' => $directory,
                'exists' => file_exists($directory),
                'writable' => is_writable($directory)
            ]);

            if (!file_exists($directory)) {
                \Log::info('Creating directory');
                mkdir($directory, 0755, true);
            }

            // Читаем содержимое файла
            $content = file_get_contents($file->getRealPath());
            \Log::info('File content read', [
                'content_length' => strlen($content)
            ]);

            // Очищаем JSON от слишком глубокой вложенности
            $content = preg_replace('/"dc_id":\d+}/', '"dc_id":2}', $content);
            $content = preg_replace('/"bytes":".*?"/', '"bytes":""', $content);
            $content = preg_replace('/"inflated":".*?"/', '"inflated":""', $content);

            // Сохраняем очищенный контент
            $filePath = "ai_files/$fileName";
            \Log::info('Attempting to save file', [
                'path' => $filePath,
                'storage_path' => storage_path('app/public/' . $filePath)
            ]);

            Storage::disk('public')->put($filePath, $content);

            \Log::info('File saved successfully');

            return response()->json([
                'success' => true,
                'data' => [
                    'file_id' => $fileName
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error uploading file', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при сохранении файла: ' . $e->getMessage()
            ], 500);
        }
    }


}
