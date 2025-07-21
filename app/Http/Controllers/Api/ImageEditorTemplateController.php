<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImageEditorTemplateController extends Controller
{
    /**
     * Получить список шаблонов редактора
     */
        public function index()
    {
        try {
            // Временная реализация - возвращаем шаблоны из файла
            $path = storage_path('app/image_editor_templates.json');

            if (!file_exists($path)) {
                return response()->json(['debug' => 'File does not exist: ' . $path]);
            }

            $content = file_get_contents($path);
            if ($content === false) {
                return response()->json(['debug' => 'Failed to read file: ' . $path]);
            }

            $templates = json_decode($content, true) ?: [];

            return response()->json(['debug' => 'File exists, content length: ' . strlen($content), 'templates' => $templates]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Сохранить шаблон редактора
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|string|in:horizontal,vertical,square',
                'bgSource' => 'required|string|in:event,upload,color',
                'bgPreview' => 'nullable|string',
                'maskId' => 'nullable|integer',
                'textLayers' => 'array',
                'imageLayers' => 'array',
                'maskType' => 'nullable|string|in:horizontal,vertical,square',
                // Дополнительные поля для шаблонов
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'preview' => 'nullable|string',
                'formatSettings' => 'nullable|array',
                'bgFileData' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Обеспечиваем, что массивы существуют
            $textLayers = $request->input('textLayers', []);
            $imageLayers = $request->input('imageLayers', []);

            if (!is_array($textLayers)) $textLayers = [];
            if (!is_array($imageLayers)) $imageLayers = [];

            // Создаем новый шаблон
            $newTemplate = [
                'id' => uniqid(),
                'format' => $request->format,
                'bgSource' => $request->bgSource,
                'bgPreview' => $request->bgPreview,
                'maskId' => $request->maskId,
                'textLayers' => $textLayers,
                'imageLayers' => $imageLayers,
                'maskType' => $request->maskType,
                // Дополнительные поля
                'name' => $request->name,
                'description' => $request->description,
                'preview' => $request->preview,
                'formatSettings' => $request->formatSettings,
                'bgFileData' => $request->bgFileData,
                'created_at' => now()->toISOString(),
            ];

            // Путь к файлу хранения
            $path = storage_path('app/image_editor_templates.json');

            // Загружаем существующие шаблоны
            $templates = [];
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $templates = json_decode($content, true) ?: [];
            }

            // Добавляем новый шаблон
            $templates[] = $newTemplate;

                        // Сохраняем обновленный список
            $result = file_put_contents($path, json_encode($templates, JSON_PRETTY_PRINT));

            if ($result === false) {
                return response()->json(['error' => 'Failed to save template file', 'path' => $path], 500);
            }

            // Возвращаем сохраненный шаблон с отладочной информацией
            return response()->json([
                'template' => $newTemplate,
                'debug' => [
                    'file_path' => $path,
                    'file_size' => $result,
                    'templates_count' => count($templates)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
