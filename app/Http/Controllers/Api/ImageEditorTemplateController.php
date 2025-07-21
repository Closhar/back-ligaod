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
            // Временная реализация - возвращаем пустой массив
            // В будущем здесь будет работа с базой данных
            return response()->json([]);
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

            // Временная реализация - возвращаем данные шаблона
            // В будущем здесь будет сохранение в базу данных
            return response()->json([
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
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
