<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Arena;
use App\Models\Event;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SportController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        $searchQuery = $request->query('q'); // Параметр поиска
        $perPage = $request->query('per_page', 15); // Количество элементов на странице
        $searchId = $request->query('id'); // Параметр поиска по ID
        $fieldParam = $request->query('field'); // Параметр для фильтрации по конкретному полю
        $title = $request->query('title');

        $query = Sport::query()
            ->select(
                'id',
                'title',
                'title_short',
                'annotation',
                'icon',
                'image',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                'slug',
                'vin')
            ->with([
                'sport_properties' => function ($query) {
                    $query->select([
                        'sport_properties.id',
                        'sport_properties.title',
                        'sport_properties.icon'
                    ]);
                }]);

        // Применяем поиск по ID, если указан
        if ($searchId) {
            $query->where('id', $searchId);
        }

        if ($title) {
            $query->where('title', '=', "{$title}");
        }

        // Применяем поиск по параметру q и field
        if ($searchQuery) {
            if ($fieldParam) {
                // Если указано конкретное поле, ищем по нему
                $query->where($fieldParam, 'LIKE', "%{$searchQuery}%");
            } else {
                // Если поле не указано, ищем по title (существующая логика)
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }
        }

        // Получаем пагинированные результаты
        $sports = $query->paginate($perPage);
        $total = $sports->total();

        return [
            'current_page' => $sports->currentPage(),
            'data' => $sports->items(),
            'first_page_url' => $sports->url(1),
            'from' => $sports->firstItem(),
            'last_page' => $sports->lastPage(),
            'last_page_url' => $sports->url($sports->lastPage()),
            'links' => $sports->links(),
            'next_page_url' => $sports->nextPageUrl(),
            'path' => $sports->path(),
            'per_page' => $sports->perPage(),
            'prev_page_url' => $sports->previousPageUrl(),
            'to' => $sports->lastItem(),
            'total' => $total,
        ];
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'string|max:255|unique:sports',
                'title_short' => 'string|max:255',
                'icon' => 'string|max:255',
                'slug' => 'string|max:255|unique:sports',
                'annotation' => 'string|max:50000|nullable',
            ]);

            $item = Sport::create($validated);

            return response()->json($item, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage() // Исправлено с errors() на getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $item = Sport::findOrFail($id);
            return response()->json($item);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Сначала валидация
            $validated = $request->validate([
                'title' => 'string|max:255|unique:sports',
                'title_short' => 'string|max:255',
                'icon' => 'string|max:255',
                'slug' => 'string|max:255|unique:sports',
                'annotation' => 'string|max:50000|nullable',
            ]);

            // Затем поиск и обновление
            $item = Sport::findOrFail($id);
            $item->update($validated);

            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $item = Sport::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function uploadImage(Request $request, $id)
    {
        try {
            $model = Sport::findOrFail($id);
            $field = $request->input('field', 'image');

            $validator = Validator::make($request->all(), [
                'image' => [
                    'required',
                    'image',
                    'mimes:jpeg,png,jpg,gif,webp',
                    'max:2048' // 2MB
                ],
                'field' => 'sometimes|string'
            ], [
                'image.required' => 'Файл изображения обязателен',
                'image.image' => 'Файл должен быть изображением',
                'image.mimes' => 'Допустимые форматы: jpeg, png, jpg, gif, webp',
                'image.max' => 'Максимальный размер файла 2MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Обработка изображения
            $path = $request->file('image')->store('sports', 'public');

            // Удаляем старое изображение
            if ($model->{$field}) {
                Storage::disk('public')->delete($model->{$field});
            }

            $model->{$field} = $path;
            $model->save();

            return response()->json([
                'success' => true,
                'image_path' => $path,
                'full_path' => Storage::disk('public')->url($path),
                'message' => 'Изображение успешно загружено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteImage(Request $request, $id)
    {
        try {
            $model = Sport::findOrFail($id);
            $field = $request->input('field', 'image');

            if (!$model->{$field}) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет изображения для удаления'
                ], 404);
            }

            Storage::disk('public')->delete($model->{$field});
            $model->{$field} = null;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => 'Изображение успешно удалено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении изображения: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyImage($id, $field = 'image')
    {
        try {
            $model = Sport::findOrFail($id);

            if (!$model->{$field}) {
                return response()->json([
                    'success' => false,
                    'message' => 'No image to delete'
                ], 404);
            }

            Storage::disk('public')->delete($model->{$field});
            $model->{$field} = null;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting image',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

}
