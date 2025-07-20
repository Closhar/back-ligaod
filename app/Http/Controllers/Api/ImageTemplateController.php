<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImageTemplateController extends Controller
{
    /**
     * Получить список шаблонов изображений
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
     * Создать новый шаблон изображения
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:horizontal,vertical,square',
                'width' => 'required|integer|min:1',
                'height' => 'required|integer|min:1',
                'image' => 'required|image|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $file = $request->file('image');
            $path = $file->store('image-templates', 'public');

            // Временная реализация - возвращаем данные файла
            // В будущем здесь будет сохранение в базу данных
            return response()->json([
                'id' => uniqid(),
                'name' => $request->name,
                'type' => $request->type,
                'width' => $request->width,
                'height' => $request->height,
                'path' => Storage::url($path),
                'preview_path' => Storage::url($path),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
