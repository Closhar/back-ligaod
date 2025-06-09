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

Пример хорошей статьи:
"Зенит" уверенно берет реванш — разгромная победа в Суперкубке над "Локомотивом"

Матч за Winline Суперкубок России между московским "Локомотивом" и петербургским "Зенитом" на "Екатеринбург Арене" собрал 9807 зрителей — второй по посещаемости результат в истории турнира.

Первый тайм: удаление и инициатива "Зенита"
Матч начался в высоком темпе с опасными моментами у обеих ворот. Уже на 7-й минуте Ола Буваро ("Локомотив") пробила в створ из-за штрафной.

Второй тайм: доминирование "Зенита" и три безответных мяча
Во втором тайме численное преимущество "Зенита" быстро дало результат. На 50-й минуте Пантюхина с фланга точно навесила во вратарскую, и Кики без сопротивления открыла счёт — 0:1.

Итоги и награды
"Зенит" завоевал свой второй Суперкубок России. Лучшим игроком встречи признана Екатерина Пантюхина (гол + результативная передача).'
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

Пример хорошей аналитической статьи:
"Зенит" уверенно берет реванш — разгромная победа в Суперкубке над "Локомотивом"

Матч за Winline Суперкубок России между московским "Локомотивом" и петербургским "Зенитом" на "Екатеринбург Арене" собрал 9807 зрителей — второй по посещаемости результат в истории турнира. Команды встретились в борьбе за трофей уже в третий раз: ранее "Локомотив" дважды завоевывал кубок, тогда как у "Зенита" на тот момент была лишь одна победа.

Первый тайм: удаление и инициатива "Зенита"
Матч начался в высоком темпе с опасными моментами у обеих ворот. Уже на 7-й минуте Ола Буваро ("Локомотив") пробила в створ из-за штрафной, а следом едва не забила Габриэла Гживиньска ("Зенит"), чей удар ногами отразила голкипер Татьяна Щербак.

Второй тайм: доминирование "Зенита" и три безответных мяча
Во втором тайме численное преимущество "Зенита" быстро дало результат. На 50-й минуте Пантюхина с фланга точно навесила во вратарскую, и Кики без сопротивления открыла счёт — 0:1.

Анализ
Ключевым фактором победы "Зенита" стало удаление Олы Буваро на 38-й минуте. Команда из Санкт-Петербурга эффективно использовала численное преимущество, контролируя 65% владения мячом.

Итоги и награды
"Зенит" завоевал свой второй Суперкубок России. Лучшим игроком встречи признана Екатерина Пантюхина (гол + результативная передача). В составе "Локомотива" наиболее активной была Полина Юкляева.'
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

        return $processedText;
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
            'max_paragraph_length' => 'nullable|integer|min:2|max:10'
        ]);

        try {
            $fullPrompt = $request->input('prompt');
            $model = $request->input('model');
            $useCache = $request->input('use_cache', true);
            $config = $this->getModelConfig($model);

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
