<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageTemplate;
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
            $templates = ImageTemplate::orderBy('created_at', 'desc')->get();
            return response()->json($templates);
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

            $template = ImageTemplate::create([
                'name' => $request->name,
                'type' => $request->type,
                'width' => $request->width,
                'height' => $request->height,
                'path' => Storage::url($path),
                'preview_path' => Storage::url($path),
            ]);

            return response()->json($template, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Показать конкретный шаблон изображения
     */
    public function show(ImageTemplate $imageTemplate)
    {
        try {
            return response()->json($imageTemplate);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Обновить шаблон изображения
     */
    public function update(Request $request, ImageTemplate $imageTemplate)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'type' => 'sometimes|string|in:horizontal,vertical,square',
                'width' => 'sometimes|integer|min:1',
                'height' => 'sometimes|integer|min:1',
                'image' => 'sometimes|image|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Обновляем основные поля
            $updateData = $request->only(['name', 'type', 'width', 'height']);

            // Если загружено новое изображение
            if ($request->hasFile('image')) {
                // Удаляем старое изображение
                if ($imageTemplate->path) {
                    $oldPath = str_replace('/storage/', '', $imageTemplate->path);
                    Storage::disk('public')->delete($oldPath);
                }

                // Загружаем новое
                $file = $request->file('image');
                $path = $file->store('image-templates', 'public');
                $updateData['path'] = Storage::url($path);
                $updateData['preview_path'] = Storage::url($path);
            }

            $imageTemplate->update($updateData);

            return response()->json($imageTemplate);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Удалить шаблон изображения
     */
    public function destroy(ImageTemplate $imageTemplate)
    {
        try {
            // Удаляем файл изображения
            if ($imageTemplate->path) {
                $path = str_replace('/storage/', '', $imageTemplate->path);
                Storage::disk('public')->delete($path);
            }

            $imageTemplate->delete();

            return response()->json(['message' => 'Шаблон изображения успешно удален']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
