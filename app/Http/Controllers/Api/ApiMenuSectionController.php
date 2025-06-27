<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuSection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ApiMenuSectionController extends Controller
{
    /**
     * Получить список разделов меню
     */
    public function index(Request $request): JsonResponse
    {
        // Если запрос для селекта (type=async), возвращаем упрощенный список
        if ($request->has('type') && $request->type === 'async') {
            $sections = MenuSection::active()
                ->ordered()
                ->select('id', 'name')
                ->get()
                ->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'name' => $section->name,
                        'title' => $section->name,
                    ];
                });

            return response()->json($sections);
        }

        $query = MenuSection::query();

        // Поиск
        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Фильтр по статусу
        if ($request->has('status') && $request->status !== null) {
            $query->where('status', $request->status);
        }

        // Сортировка
        $sortField = $request->get('sort_field', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Пагинация
        $perPage = $request->get('per_page', 30);
        $sections = $query->paginate($perPage);

        return response()->json($sections);
    }

    /**
     * Получить раздел меню по ID
     */
    public function show($id): JsonResponse
    {
        $section = MenuSection::findOrFail($id);
        return response()->json($section);
    }

    /**
     * Создать новый раздел меню
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Устанавливаем значение по умолчанию для sort_order если не передано или пустое
        $data = $request->all();
        if (!isset($data['sort_order']) || $data['sort_order'] === null || $data['sort_order'] === '') {
            $data['sort_order'] = 0;
        }

        $section = MenuSection::create($data);
        return response()->json($section, 201);
    }

    /**
     * Обновить раздел меню
     */
    public function update(Request $request, $id): JsonResponse
    {
        $section = MenuSection::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Устанавливаем значение по умолчанию для sort_order если не передано или пустое
        $data = $request->all();
        if (!isset($data['sort_order']) || $data['sort_order'] === null || $data['sort_order'] === '') {
            $data['sort_order'] = 0;
        }

        $section->update($data);
        return response()->json($section);
    }

    /**
     * Удалить раздел меню
     */
    public function destroy($id): JsonResponse
    {
        $section = MenuSection::findOrFail($id);

        // Проверяем, есть ли связанные страницы
        if ($section->adminPages()->count() > 0) {
            return response()->json([
                'error' => 'Нельзя удалить раздел, содержащий страницы'
            ], 422);
        }

        $section->delete();
        return response()->json(['message' => 'Раздел меню удален']);
    }

    /**
     * Получить список разделов для селекта
     */
    public function getForSelect(): JsonResponse
    {
        $sections = MenuSection::active()
            ->ordered()
            ->select('id', 'name')
            ->get()
            ->map(function ($section) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'title' => $section->name,
                ];
            });

        return response()->json($sections);
    }
}