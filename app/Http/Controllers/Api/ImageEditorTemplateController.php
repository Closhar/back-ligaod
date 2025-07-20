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
                'format' => 'required|string',
                'bgSource' => 'required|string',
                'bgPreview' => 'nullable|string',
                'maskId' => 'nullable|integer',
                'textLayers' => 'required|array',
                'imageLayers' => 'required|array',
                'maskType' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Временная реализация - возвращаем данные шаблона
            // В будущем здесь будет сохранение в базу данных
            return response()->json([
                'id' => uniqid(),
                'format' => $request->format,
                'bgSource' => $request->bgSource,
                'bgPreview' => $request->bgPreview,
                'maskId' => $request->maskId,
                'textLayers' => $request->textLayers,
                'imageLayers' => $request->imageLayers,
                'maskType' => $request->maskType,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
