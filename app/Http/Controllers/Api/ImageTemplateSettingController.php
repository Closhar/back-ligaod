<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImageTemplateSettingController extends Controller
{
    /**
     * Получить список настроек форматов
     */
    public function index()
    {
        try {
            // Временная реализация - возвращаем базовые форматы
            return response()->json([
                [
                    'id' => 1,
                    'name' => 'Горизонтальный',
                    'type' => 'horizontal',
                    'width' => 1200,
                    'height' => 630,
                ],
                [
                    'id' => 2,
                    'name' => 'Вертикальный',
                    'type' => 'vertical',
                    'width' => 630,
                    'height' => 1200,
                ],
                [
                    'id' => 3,
                    'name' => 'Квадратный',
                    'type' => 'square',
                    'width' => 800,
                    'height' => 800,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Создать новую настройку формата
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:horizontal,vertical,square',
                'width' => 'required|integer|min:1',
                'height' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Временная реализация - возвращаем данные
            // В будущем здесь будет сохранение в базу данных
            return response()->json([
                'id' => uniqid(),
                'name' => $request->name,
                'type' => $request->type,
                'width' => $request->width,
                'height' => $request->height,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Обновить настройку формата
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:horizontal,vertical,square',
                'width' => 'required|integer|min:1',
                'height' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Временная реализация - возвращаем обновленные данные
            // В будущем здесь будет обновление в базе данных
            return response()->json([
                'id' => $id,
                'name' => $request->name,
                'type' => $request->type,
                'width' => $request->width,
                'height' => $request->height,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Удалить настройку формата
     */
    public function destroy($id)
    {
        try {
            // Временная реализация - возвращаем успех
            // В будущем здесь будет удаление из базы данных
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
