<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PositionController extends Controller
{
    /**
     * Получить список всех должностей с пагинацией
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query();

        // Поиск по названию
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Фильтрация по активности
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $positions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $positions->items(),
            'pagination' => [
                'current_page' => $positions->currentPage(),
                'last_page' => $positions->lastPage(),
                'per_page' => $positions->perPage(),
                'total' => $positions->total(),
            ]
        ]);
    }

    /**
     * Получить конкретную должность
     */
    public function show(Position $position): JsonResponse
    {
        $position->load(['people', 'activeMemberships.person']);

        return response()->json([
            'success' => true,
            'data' => $position
        ]);
    }

    /**
     * Создать новую должность
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:positions,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $position = Position::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Должность успешно создана',
            'data' => $position
        ], 201);
    }

    /**
     * Обновить должность
     */
    public function update(Request $request, Position $position): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:positions,name,' . $position->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $position->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Должность успешно обновлена',
            'data' => $position
        ]);
    }

    /**
     * Удалить должность
     */
    public function destroy(Position $position): JsonResponse
    {
        // Проверяем, есть ли активные членства
        if ($position->activeMemberships()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить должность, у которой есть активные членства'
            ], 422);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Должность успешно удалена'
        ]);
    }

    /**
     * Получить статистику по должностям
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Position::count(),
            'active' => Position::where('is_active', true)->count(),
            'inactive' => Position::where('is_active', false)->count(),
            'with_people' => Position::whereHas('activeMemberships')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
