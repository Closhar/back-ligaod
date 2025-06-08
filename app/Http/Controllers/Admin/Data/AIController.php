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
            'temperature' => 0.7,
            'cost_per_1k_input' => 0.002,
            'cost_per_1k_output' => 0.002,
            'system_prompt' => 'Вы - спортивный журналист.'
        ],
        'gpt-4-turbo-preview' => [
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
            'system_prompt' => 'Вы - опытный спортивный журналист, специализирующийся на написании качественных аналитических статей.'
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

    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:4000',
            'file_id' => 'nullable|string',
            'model' => 'nullable|string|in:gpt-3.5-turbo,gpt-4-turbo-preview',
            'use_cache' => 'nullable|boolean'
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
            ])->post('https://api.openai.com/v1/chat/completions', [
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
                'temperature' => $config['temperature'],
                'max_tokens' => $config['max_tokens'],
                'presence_penalty' => 0.1,
                'frequency_penalty' => 0.1
            ]);

            if (!$response->successful()) {
                throw new \Exception('Ошибка при обращении к AI API');
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'];

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
                    'temperature' => $config['temperature']
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
