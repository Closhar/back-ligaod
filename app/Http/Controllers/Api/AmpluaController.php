<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amplua;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AmpluaController extends Controller
{
    /**
     * Получить список всех амплуа с пагинацией
     */
    public function index(Request $request): JsonResponse
    {
        $query = Amplua::query();

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
        $ampluas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $ampluas->items(),
            'pagination' => [
                'current_page' => $ampluas->currentPage(),
                'last_page' => $ampluas->lastPage(),
                'per_page' => $ampluas->perPage(),
                'total' => $ampluas->total(),
            ]
        ]);
    }

    /**
     * Получить конкретное амплуа
     */
    public function show(Amplua $amplua): JsonResponse
    {
        $amplua->load(['people', 'activeMemberships.person']);

        return response()->json([
            'success' => true,
            'data' => $amplua
        ]);
    }

    /**
     * Создать новое амплуа
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:ampluas,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $amplua = Amplua::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Амплуа успешно создано',
            'data' => $amplua
        ], 201);
    }

    /**
     * Обновить амплуа
     */
    public function update(Request $request, Amplua $amplua): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:ampluas,name,' . $amplua->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $amplua->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Амплуа успешно обновлено',
            'data' => $amplua
        ]);
    }

    /**
     * Удалить амплуа
     */
    public function destroy(Amplua $amplua): JsonResponse
    {
        // Проверяем, есть ли активные членства
        if ($amplua->activeMemberships()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить амплуа, у которого есть активные членства'
            ], 422);
        }

        $amplua->delete();

        return response()->json([
            'success' => true,
            'message' => 'Амплуа успешно удалено'
        ]);
    }

    /**
     * Получить статистику по амплуа
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Amplua::count(),
            'active' => Amplua::where('is_active', true)->count(),
            'inactive' => Amplua::where('is_active', false)->count(),
            'with_people' => Amplua::whereHas('activeMemberships')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
