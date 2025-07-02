<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Получить список всех ролей
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query();

        // Фильтрация по типу
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Фильтрация по активности
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Поиск по названию
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $roles = $query->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Получить конкретную роль
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['activeMemberships.person']);

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    /**
     * Создать новую роль
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:sportsman,non_sportsman',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Роль успешно создана',
            'data' => $role
        ], 201);
    }

    /**
     * Обновить роль
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:sportsman,non_sportsman',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $role->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Роль успешно обновлена',
            'data' => $role
        ]);
    }

    /**
     * Удалить роль
     */
    public function destroy(Role $role): JsonResponse
    {
        // Проверяем, есть ли активные членства в этой роли
        if ($role->activeMemberships()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить роль, к которой привязаны активные персоны'
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Роль успешно удалена'
        ]);
    }

    /**
     * Получить список ролей для спортсменов
     */
    public function sportsman(): JsonResponse
    {
        $roles = Role::sportsman()->active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Получить список ролей для не спортсменов
     */
    public function nonSportsman(): JsonResponse
    {
        $roles = Role::nonSportsman()->active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Получить статистику по ролям
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Role::count(),
            'sportsman' => Role::sportsman()->count(),
            'non_sportsman' => Role::nonSportsman()->count(),
            'active' => Role::active()->count(),
            'with_people' => Role::whereHas('activeMemberships')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
