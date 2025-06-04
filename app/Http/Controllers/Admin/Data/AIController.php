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
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'file_id' => 'nullable|string'
        ]);

        try {
            $fullPrompt = $request->input('prompt');

            // Если есть file_id, добавляем содержимое файла к промту
            if ($request->has('file_id')) {
                $filePath = storage_path('app/public/ai_files/' . $request->file_id);
                if (file_exists($filePath)) {
                    $fileContent = file_get_contents($filePath);
                    $fullPrompt .= "\n\nСодержимое файла:\n" . $fileContent;
                }
            }

            // Отправляем запрос к AI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key')
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $fullPrompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000
            ]);

            if (!$response->successful()) {
                throw new \Exception('Ошибка при обращении к AI API');
            }

            return response()->json([
                'success' => true,
                'data' => $response->json()['choices'][0]['message']['content']
            ]);

        } catch (\Exception $e) {
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
