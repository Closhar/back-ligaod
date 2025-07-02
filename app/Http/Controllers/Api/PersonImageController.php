<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PersonImageController extends Controller
{
    /**
     * Получить все изображения персоны
     */
    public function index(Person $person): JsonResponse
    {
        $images = $person->images()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    }

    /**
     * Загрузить новое изображение для персоны
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imageFile = $request->file('image');
        $fileName = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
        $path = 'person/' . $person->id . '/' . $fileName;

        // Сохраняем файл
        Storage::disk('public')->put($path, file_get_contents($imageFile));

        // Определяем порядок сортировки
        $sortOrder = $request->sort_order ?? $person->images()->max('sort_order') + 1;

        // Создаем запись в базе данных
        $personImage = PersonImage::create([
            'person_id' => $person->id,
            'image_path' => $path,
            'alt_text' => $request->alt_text,
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Изображение успешно загружено',
            'data' => $personImage
        ], 201);
    }

    /**
     * Обновить изображение
     */
    public function update(Request $request, Person $person, PersonImage $image): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $image->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Изображение успешно обновлено',
            'data' => $image
        ]);
    }

    /**
     * Удалить изображение
     */
    public function destroy(Person $person, PersonImage $image): JsonResponse
    {
        // Удаляем файл с диска
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Изображение успешно удалено'
        ]);
    }

    /**
     * Изменить порядок изображений
     */
    public function reorder(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image_orders' => 'required|array',
            'image_orders.*.id' => 'required|exists:person_images,id',
            'image_orders.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->image_orders as $order) {
            PersonImage::where('id', $order['id'])
                ->where('person_id', $person->id)
                ->update(['sort_order' => $order['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Порядок изображений успешно изменен'
        ]);
    }
}
