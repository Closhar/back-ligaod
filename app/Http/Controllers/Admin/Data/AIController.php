<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function generate(Request $request)
    {
        try {
            // Логируем входящий запрос
            Log::info('AI Generation Request', [
                'prompt' => $request->prompt
            ]);

            $request->validate([
                'prompt' => 'required|string|min:3|max:1000'
            ]);

            // Проверяем наличие API ключа
            $apiKey = config('services.openai.api_key');
            if (empty($apiKey)) {
                Log::error('OpenAI API Key is not set');
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI API Key is not configured'
                ], 500);
            }

            // Логируем запрос к OpenAI
            Log::info('Sending request to OpenAI', [
                'model' => 'gpt-3.5-turbo',
                'prompt_length' => strlen($request->prompt)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты - помощник для создания спортивных новостей и отчетов. Твоя задача - создавать качественные, информативные и интересные тексты о спортивных событиях. Используй эмодзи для улучшения читаемости. Форматируй текст с помощью Markdown.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $request->prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000
            ]);

            // Логируем ответ от OpenAI
            Log::info('OpenAI Response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при обращении к OpenAI API: ' . ($response->json()['error']['message'] ?? 'Unknown error')
                ], 500);
            }

            $result = $response->json();

            if (!isset($result['choices'][0]['message']['content'])) {
                Log::error('Invalid OpenAI Response Structure', [
                    'response' => $result
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Неверная структура ответа от OpenAI'
                ], 500);
            }

            $content = $result['choices'][0]['message']['content'];

            // Логируем успешный результат
            Log::info('AI Generation Success', [
                'content_length' => strlen($content)
            ]);

            return response()->json([
                'success' => true,
                'data' => $content
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации: ' . implode(', ', array_merge(...array_values($e->errors())))
            ], 422);
        } catch (\Exception $e) {
            Log::error('AI Generation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при генерации контента: ' . $e->getMessage()
            ], 500);
        }
    }



}
