<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminPage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminPageController extends Controller
{
//    public function __construct()
//    {
//        $this->middleware('auth:sanctum');
//    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->query('q'); // Параметр поиска
        $perPage = $request->query('per_page', 10); // Количество элементов на странице
        $searchId = $request->query('id'); // Параметр поиска по ID
        $fieldParam = $request->query('field'); // Параметр для фильтрации по конкретному полю

        $query = AdminPage::query()
            ->select(
                'id',
                'title',
                'description',
                'slug',
                'icon',
                'image',
                'menu'
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
                // Если поле не указано, ищем по нескольким полям
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('title', 'like', "%{$searchQuery}%")
                        ->orWhere('description', 'like', "%{$searchQuery}%")
                        ->orWhere('slug', 'like', "%{$searchQuery}%");
                });
            }
        }

        // Сортировка
        if ($request->has('sort_field')) {
            $sortDirection = $request->input('sort_direction', 'asc');
            $query->orderBy($request->input('sort_field'), $sortDirection);
        }

        // Получаем пагинированные результаты
        $items = $query->paginate($perPage);
        $total = $items->total();

        return [
            'current_page' => $items->currentPage(),
            'data' => $items->items(),
            'first_page_url' => $items->url(1),
            'from' => $items->firstItem(),
            'last_page' => $items->lastPage(),
            'last_page_url' => $items->url($items->lastPage()),
            'links' => $items->links(),
            'next_page_url' => $items->nextPageUrl(),
            'path' => $items->path(),
            'per_page' => $items->perPage(),
            'prev_page_url' => $items->previousPageUrl(),
            'to' => $items->lastItem(),
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
                // Добавьте другие поля при необходимости
            ]);

            $item = AdminPage::create($validated);

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
            $item = AdminPage::findOrFail($id);
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
                // Добавьте другие поля при необходимости
            ]);

            // Затем поиск и обновление
            $item = AdminPage::findOrFail($id);
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
            $item = AdminPage::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
