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
            $request->validate([
                'prompt' => 'required|string|min:3|max:1000'
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
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

            if (!$response->successful()) {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при обращении к OpenAI API'
                ], 500);
            }
            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? '';

            return response()->json([
                'success' => true,
                'data' => $content
            ]);

        } catch (\Exception $e) {
            Log::error('AI Generation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при генерации контента'
            ], 500);
        }
    }
}
