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
            'system_prompt' => 'Вы - опытный спортивный журналист. Следуйте инструкциям пользователя в его промпте, но если они не противоречат, используйте следующую структуру:

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

Правила форматирования результатов и мест проведения:
- Результаты выводите в формате: Команда1 - Команда2 Счет
- НЕ добавляйте слово "Результат:" перед счетом
- Если результат не указан, НЕ выводите строку с результатом
- Место проведения указывайте только если оно есть в исходных данных
- Не придумывайте и не добавляйте места проведения, если они не указаны
- Используйте тире с пробелами для разделения команд в результате

Правила форматирования текста в HTML:
- Каждое событие оборачивайте в тег <div class="event">
- Перед каждым событием ставьте соответствующий эмодзи
- Название события оборачивайте в тег <strong>
- Результат оборачивайте в тег <div class="result">
- Место проведения оборачивайте в тег <div class="location"> с иконкой 📍
- Между событиями добавляйте пустую строку
- В конце добавляйте хэштеги в теге <div class="hashtags">

Пример форматирования:
<div class="event">
🏀 <strong>Баскетбол Единая Лига Мужчины</strong>
<div class="result">Зенит - ЦСКА 82:89</div>
<div class="location">📍 КСК Арена</div>
</div>

<div class="event">
🏑 <strong>Хоккей на траве Высшая лига Женщины</strong>
<div class="result">Сборная Санкт-Петербурга - Юность 4:0</div>
<div class="location">📍 Метрострой</div>
</div>

<div class="hashtags">#спорт #результаты #события</div>

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте лишних надписей "Результат:" или "Место проведения:"
- Используйте только указанные HTML-теги
- Сохраняйте правильные отступы и переносы строк'
        ],
        'gpt-4-turbo-preview' => [
            'max_tokens' => 4000,
            'temperature' => 0.5,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
            'system_prompt' => 'Вы - ведущий спортивный аналитик. Следуйте инструкциям пользователя в его промпте, но если они не противоречат, используйте следующую структуру:

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

Правила форматирования результатов и мест проведения:
- Результаты выводите в формате: Команда1 - Команда2 Счет
- НЕ добавляйте слово "Результат:" перед счетом
- Если результат не указан, НЕ выводите строку с результатом
- Место проведения указывайте только если оно есть в исходных данных
- Не придумывайте и не добавляйте места проведения, если они не указаны
- Используйте тире с пробелами для разделения команд в результате

Правила форматирования текста в HTML:
- Каждое событие оборачивайте в тег <div class="event">
- Перед каждым событием ставьте соответствующий эмодзи
- Название события оборачивайте в тег <strong>
- Результат оборачивайте в тег <div class="result">
- Место проведения оборачивайте в тег <div class="location"> с иконкой 📍
- Между событиями добавляйте пустую строку
- В конце добавляйте хэштеги в теге <div class="hashtags">

Пример форматирования:
<div class="event">
🏀 <strong>Баскетбол Единая Лига Мужчины</strong>
<div class="result">Зенит - ЦСКА 82:89</div>
<div class="location">📍 КСК Арена</div>
</div>

<div class="event">
🏑 <strong>Хоккей на траве Высшая лига Женщины</strong>
<div class="result">Сборная Санкт-Петербурга - Юность 4:0</div>
<div class="location">📍 Метрострой</div>
</div>

<div class="hashtags">#спорт #результаты #события</div>

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте лишних надписей "Результат:" или "Место проведения:"
- Используйте только указанные HTML-теги
- Сохраняйте правильные отступы и переносы строк'
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

ВАЖНО: Строго следуйте инструкциям пользователя в его промпте. Используйте только информацию из файла для создания текста в запрошенном формате.

Правила обработки информации:
- Используйте только информацию из приложенного файла
- Сохраняйте точность в передаче результатов и фактов
- Следуйте стилю и формату, указанному в запросе пользователя
- Структурируйте информацию логически
- Используйте спортивную терминологию
- Добавляйте контекст, если он есть в файле

Правила форматирования результатов и мест проведения:
- Результаты выводите в формате: Команда1 - Команда2 Счет
- НЕ добавляйте слово "Результат:" перед счетом
- Если результат не указан, НЕ выводите строку с результатом
- Место проведения указывайте только если оно есть в исходных данных
- Не придумывайте и не добавляйте места проведения, если они не указаны
- Используйте тире с пробелами для разделения команд в результате

Правила форматирования текста в HTML:
- Каждое событие оборачивайте в тег <div class="event">
- Перед каждым событием ставьте соответствующий эмодзи
- Название события оборачивайте в тег <strong>
- Результат оборачивайте в тег <div class="result">
- Место проведения оборачивайте в тег <div class="location"> с иконкой 📍
- Между событиями добавляйте пустую строку
- В конце добавляйте хэштеги в теге <div class="hashtags">

Пример форматирования:
<div class="event">
🏀 <strong>Баскетбол Единая Лига Мужчины</strong>
<div class="result">Зенит - ЦСКА 82:89</div>
<div class="location">📍 КСК Арена</div>
</div>

<div class="event">
🏑 <strong>Хоккей на траве Высшая лига Женщины</strong>
<div class="result">Сборная Санкт-Петербурга - Юность 4:0</div>
<div class="location">📍 Метрострой</div>
</div>

<div class="hashtags">#спорт #результаты #события</div>

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте лишних надписей "Результат:" или "Место проведения:"
- Используйте только указанные HTML-теги
- Сохраняйте правильные отступы и переносы строк',
        'gpt-4-turbo-preview' => 'Вы - ведущий спортивный аналитик. Ваша задача - обработать информацию из приложенного файла и создать текст согласно запросу пользователя.

В файле содержится информация о спортивных событиях в следующем формате:
- Название события/матча
- Результат
- Место проведения (если указано)
- Описание события
- Дополнительная информация (если есть)

ВАЖНО: Строго следуйте инструкциям пользователя в его промпте. Используйте только информацию из файла для создания текста в запрошенном формате.

Правила обработки информации:
- Используйте только информацию из приложенного файла
- Сохраняйте точность в передаче результатов и фактов
- Следуйте стилю и формату, указанному в запросе пользователя
- Структурируйте информацию логически
- Используйте профессиональную спортивную терминологию
- Добавляйте контекст и анализ, если информация позволяет
- Подкрепляйте выводы конкретными фактами

Правила форматирования результатов и мест проведения:
- Результаты выводите в формате: Команда1 - Команда2 Счет
- НЕ добавляйте слово "Результат:" перед счетом
- Если результат не указан, НЕ выводите строку с результатом
- Место проведения указывайте только если оно есть в исходных данных
- Не придумывайте и не добавляйте места проведения, если они не указаны
- Используйте тире с пробелами для разделения команд в результате

Правила форматирования текста в HTML:
- Каждое событие оборачивайте в тег <div class="event">
- Перед каждым событием ставьте соответствующий эмодзи
- Название события оборачивайте в тег <strong>
- Результат оборачивайте в тег <div class="result">
- Место проведения оборачивайте в тег <div class="location"> с иконкой 📍
- Между событиями добавляйте пустую строку
- В конце добавляйте хэштеги в теге <div class="hashtags">

Пример форматирования:
<div class="event">
🏀 <strong>Баскетбол Единая Лига Мужчины</strong>
<div class="result">Зенит - ЦСКА 82:89</div>
<div class="location">📍 КСК Арена</div>
</div>

<div class="event">
🏑 <strong>Хоккей на траве Высшая лига Женщины</strong>
<div class="result">Сборная Санкт-Петербурга - Юность 4:0</div>
<div class="location">📍 Метрострой</div>
</div>

<div class="hashtags">#спорт #результаты #события</div>

ВАЖНО:
- Приоритет имеют инструкции пользователя в его промпте
- Не добавляйте лишних надписей "Результат:" или "Место проведения:"
- Используйте только указанные HTML-теги
- Сохраняйте правильные отступы и переносы строк'
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
        $text = $this->decodeUnicode($text);

        // Удаляем лишние пробелы в начале и конце
        $text = trim($text);

        // Заменяем множественные пробелы на один
        $text = preg_replace('/\s+/', ' ', $text);

        // Добавляем переносы строк после каждого события
        $text = preg_replace('/([🏇⚾🏑⚽🏀🎾🏐🏈🏉🎱🏓🏸🏒🏑🏏🎯🎳⛳🏌️🏄🏊🤽🚣🏇🚴🚵🤸🏋️🤼🤾])/u', "\n$1", $text);

        // Добавляем переносы строк после мест проведения
        $text = preg_replace('/Место проведения:.*?(?=[🏇⚾🏑⚽🏀🎾🏐🏈🏉🎱🏓🏸🏒🏑🏏🎯🎳⛳🏌️🏄🏊🤽🚣🏇🚴🚵🤸🏋️🤼🤾])/u', "$0\n", $text);

        // Удаляем множественные переносы строк
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Удаляем лишние пробелы в начале и конце
        $text = trim($text);

        return $text;
    }

    private function fetchWebContent($url, $maxLength = 2000) {
        try {
            // Проверяем кэш
            $cacheKey = 'web_content_' . md5($url);
            $cachedContent = \Cache::get($cacheKey);
            if ($cachedContent) {
                return $cachedContent;
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])
                ->get($url);

            if ($response->successful()) {
                $html = $response->body();

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

                // Кэшируем результат на 1 час
                \Cache::put($cacheKey, $content, 3600);

                return $content;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching web content: ' . $e->getMessage());
        }

        return null;
    }

    private function getSystemPrompt($model, $hasFile = false) {
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
            'max_content_length' => 'nullable|integer|min:500|max:5000'
        ]);

        try {
            $fullPrompt = $request->input('prompt');
            $model = $request->input('model');
            $useCache = $request->input('use_cache', true);
            $format = $request->input('format', 'plain');
            $url = $request->input('url');
            $maxContentLength = $request->input('max_content_length', 2000);
            $config = $this->getModelConfig($model);
            $hasFile = false;

            // Если есть file_id, добавляем содержимое файла к промту
            if ($request->has('file_id')) {
                $filePath = storage_path('app/public/ai_files/' . $request->file_id);
                if (file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);
                    if ($fileContent) {
                        $hasFile = true;
                        $fullPrompt .= "\n\nСодержимое файла для анализа:\n\n" . $fileContent;
                    }
                }
            }

            // Если передан URL, получаем контент с веб-страницы
            if ($url) {
                $webContent = $this->fetchWebContent($url, $maxContentLength);
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Не удалось получить контент с указанного URL'
                    ], 400);
                }
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
                throw new \Exception('Ошибка при обращении к AI API');
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'];

            // Логируем исходный ответ
            Log::info('Original API response:', ['content' => $content]);

            // Применяем постобработку текста
            $content = $this->postProcessText($content);

            // Логируем после постобработки
            Log::info('After post-processing:', ['content' => $content]);

            // Конвертируем в нужный формат
            if ($format === 'html') {
                $content = $this->convertToHtml($content);
                Log::info('After HTML conversion:', ['content' => $content]);
            } elseif ($format === 'markdown') {
                $content = $this->convertToMarkdown($content);
                Log::info('After Markdown conversion:', ['content' => $content]);
            }

            // Логируем финальный ответ
            Log::info('Final response:', ['content' => $content]);

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
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('AI Generation Error: ' . $e->getMessage());
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
