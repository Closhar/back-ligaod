<?php

namespace App\Http\Controllers;

use App\Models\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeasonController extends Controller
{
    /**
     * Получить список всех сезонов
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Season::query();

            // Фильтрация по активности
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Поиск по названию
            if ($request->has('search') && $request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('name', 'like', '%' . $request->search . '%');
                });
            }

            // Сортировка
            $sortField = $request->get('sort_field', 'title');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // Пагинация
            $perPage = $request->get('per_page', 15);
            $seasons = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $seasons->items(),
                'pagination' => [
                    'current_page' => $seasons->currentPage(),
                    'last_page' => $seasons->lastPage(),
                    'per_page' => $seasons->perPage(),
                    'total' => $seasons->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения списка сезонов: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить конкретный сезон
     */
    public function show($id): JsonResponse
    {
        try {
            $season = Season::find($id);

            if (!$season) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не найден'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $season
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения сезона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создать новый сезон
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255|unique:seasons,title',
                'name' => 'nullable|string|max:255',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $season = Season::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Сезон успешно создан',
                'data' => $season
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка создания сезона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить сезон
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $season = Season::find($id);

            if (!$season) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не найден'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255|unique:seasons,title,' . $id,
                'name' => 'nullable|string|max:255',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $season->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Сезон успешно обновлен',
                'data' => $season
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка обновления сезона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить сезон
     */
    public function destroy($id): JsonResponse
    {
        try {
            $season = Season::find($id);

            if (!$season) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не найден'
                ], 404);
            }

            // Проверяем, используется ли сезон в competition_seasons
            if ($season->competitionSeasons()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалить сезон, который используется в соревнованиях'
                ], 422);
            }

            $season->delete();

            return response()->json([
                'success' => true,
                'message' => 'Сезон успешно удален'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка удаления сезона: ' . $e->getMessage()
            ], 500);
        }
    }
}
