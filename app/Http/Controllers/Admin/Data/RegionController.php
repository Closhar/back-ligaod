<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegionController extends Controller
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
        $type = $request->query('type'); // Параметр для изменения структуры ответа

        $query = Region::query()
            ->select(
                'id',
                'title',
                'title_short',
                'subdomain'
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

        // Для async запросов
        if ($type === 'async') {
            return $query->get()->map(function ($region) {
                return [
                    'id' => $region->id,
                    'title' => $region->title,
                    'title_short' => $region->title_short,
                    'subdomain' => $region->subdomain
                ];
            })->toArray();
        }

        // Получаем пагинированные результаты
        $regions = $query->paginate($perPage);
        $total = $regions->total();

        return [
            'current_page' => $regions->currentPage(),
            'data' => $regions->items(),
            'first_page_url' => $regions->url(1),
            'from' => $regions->firstItem(),
            'last_page' => $regions->lastPage(),
            'last_page_url' => $regions->url($regions->lastPage()),
            'links' => $regions->links(),
            'next_page_url' => $regions->nextPageUrl(),
            'path' => $regions->path(),
            'per_page' => $regions->perPage(),
            'prev_page_url' => $regions->previousPageUrl(),
            'to' => $regions->lastItem(),
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
                'title_short' => 'required|string|max:255',
                'subdomain' => 'string|max:255|unique:regions',
            ]);

            $item = Region::create($validated);

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
            $item = Region::findOrFail($id);
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
                'title_short' => 'string|max:255',
                'subdomain' => "string|max:255|nullable|unique:regions,subdomain,{$id}",
            ]);

            // Затем поиск и обновление
            $item = Region::findOrFail($id);
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
            $item = Region::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
