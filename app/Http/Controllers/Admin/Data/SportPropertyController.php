<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\SportProperty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SportPropertyController extends Controller
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

        $query = SportProperty::query()
            ->select(
                'id',
                'title',
                'annotation',
                'icon'
            );

        // Применяем поиск по ID, если указан
        if ($searchId) {
            $query->where('id', $searchId);
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
        $sportProperties = $query->paginate($perPage);
        $total = $sportProperties->total();

        return [
            'current_page' => $sportProperties->currentPage(),
            'data' => $sportProperties->items(),
            'first_page_url' => $sportProperties->url(1),
            'from' => $sportProperties->firstItem(),
            'last_page' => $sportProperties->lastPage(),
            'last_page_url' => $sportProperties->url($sportProperties->lastPage()),
            'links' => $sportProperties->links(),
            'next_page_url' => $sportProperties->nextPageUrl(),
            'path' => $sportProperties->path(),
            'per_page' => $sportProperties->perPage(),
            'prev_page_url' => $sportProperties->previousPageUrl(),
            'to' => $sportProperties->lastItem(),
            'total' => $total,
        ];
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255|unique:sport_properties',
                'annotation' => 'string|max:50000|nullable',
                'icon' => 'required|string|max:255',
            ]);

            $item = SportProperty::create($validated);

            return response()->json($item, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $item = SportProperty::findOrFail($id);
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
                'title' => 'string|max:255|unique:sport_properties,title,'.$id,
                'annotation' => 'string|max:50000|nullable',
                'icon' => 'string|max:255',
            ]);

            // Затем поиск и обновление
            $item = SportProperty::findOrFail($id);
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
            $item = SportProperty::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

}
