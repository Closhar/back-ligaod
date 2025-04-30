<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SeriesController extends Controller
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

        $query = Series::query()
            ->select(
                'id',
                'title',
                'match_info',
                'title_short',
                'description',
                'series_type_id'
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
                $query->where('title', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('match_info', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('title_short', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('description', 'LIKE', "%{$searchQuery}%");
            }
        }

        // Для async запросов
        if ($type === 'async') {
            return $query->limit($limit)->get()->map(function ($series) {
                return [
                    'id' => $series->id,
                    'title' => $series->title,
                    'title_short' => $series->title_short,
                    'match_info' => $series->match_info,
                    'description' => $series->description,
                    'series_type_id' => $series->series_type_id
                ];
            })->toArray();
        }

        // Получаем пагинированные результаты
        $series = $query->paginate($perPage);
        $total = $series->total();

        return [
            'current_page' => $series->currentPage(),
            'data' => $series->items(),
            'first_page_url' => $series->url(1),
            'from' => $series->firstItem(),
            'last_page' => $series->lastPage(),
            'last_page_url' => $series->url($series->lastPage()),
            'links' => $series->links(),
            'next_page_url' => $series->nextPageUrl(),
            'path' => $series->path(),
            'per_page' => $series->perPage(),
            'prev_page_url' => $series->previousPageUrl(),
            'to' => $series->lastItem(),
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
                'match_info' => 'nullable|string|max:255',
                'title_short' => 'required|string|max:255|unique:series,title_short',
                'description' => 'nullable|string|max:50000',
                'event_type' => 'string|max:255',
            ]);

            $data = $validated;
            if (isset($data['event_type'])) {
                $data['is_series'] = $data['event_type'];
                unset($data['event_type']);
            }

            $item = Series::create($data);

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
            $item = Series::findOrFail($id);
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
                'match_info' => 'nullable|string|max:255',
                'title_short' => 'string|max:255|unique:series,title_short,' . $id,
                'description' => 'nullable|string|max:50000',
                'is_series' => 'string|max:255',
            ]);

            // Затем поиск и обновление
            $item = Series::findOrFail($id);
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
            $item = Series::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
