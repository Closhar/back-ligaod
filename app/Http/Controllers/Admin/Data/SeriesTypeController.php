<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\SeriesType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeriesTypeController extends Controller
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
        $type = $request->query('type'); // Параметр типа ответа
        $limit = $request->query('limit', $perPage); // Лимит для async запросов

        $query = SeriesType::query()
            ->select(
                'id',
                'title',
                'title_short'
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
                // Если поле не указано, ищем по title и title_short
                $query->where('title', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('title_short', 'LIKE', "%{$searchQuery}%");
            }
        }

        // Для async запросов
        if ($type === 'async') {
            return $query->limit($limit)->get()->map(function ($seriesType) {
                return [
                    'id' => $seriesType->id,
                    'title' => $seriesType->title,
                    'title_short' => $seriesType->title_short
                ];
            })->toArray();
        }

        // Получаем пагинированные результаты
        $seriesTypes = $query->paginate($perPage);
        $total = $seriesTypes->total();

        return [
            'current_page' => $seriesTypes->currentPage(),
            'data' => $seriesTypes->items(),
            'first_page_url' => $seriesTypes->url(1),
            'from' => $seriesTypes->firstItem(),
            'last_page' => $seriesTypes->lastPage(),
            'last_page_url' => $seriesTypes->url($seriesTypes->lastPage()),
            'links' => $seriesTypes->links(),
            'next_page_url' => $seriesTypes->nextPageUrl(),
            'path' => $seriesTypes->path(),
            'per_page' => $seriesTypes->perPage(),
            'prev_page_url' => $seriesTypes->previousPageUrl(),
            'to' => $seriesTypes->lastItem(),
            'total' => $total,
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'title_short' => 'required|string|max:255|unique:series_types,title_short',
            ]);

            $item = SeriesType::create($validated);

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

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = SeriesType::findOrFail($id);
            return response()->json($item);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            // Сначала валидация
            $validated = $request->validate([
                'title' => 'string|max:255',
                'title_short' => 'string|max:255|unique:series_types,title_short,' . $id,
            ]);

            // Затем поиск и обновление
            $item = SeriesType::findOrFail($id);
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $item = SeriesType::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
