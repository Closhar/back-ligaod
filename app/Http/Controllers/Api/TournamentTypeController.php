<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TournamentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TournamentTypeController extends Controller
{
    /**
     * Получить все типы турниров
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'active_only' => 'boolean'
        ]);

        $query = TournamentType::with('points');

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $tournamentTypes = $query->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tournamentTypes
        ]);
    }

    /**
     * Получить конкретный тип турнира
     */
    public function show(int $id): JsonResponse
    {
        $tournamentType = TournamentType::with('points')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $tournamentType
        ]);
    }

    /**
     * Создать новый тип турнира
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:tournament_types,code',
            'name' => 'required|string|max:255',
            'color_class' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'ignore_teams_multiplier' => 'boolean',
            'coefficient' => 'numeric|min:0.1|max:2.0',
            'participation_points' => 'integer|min:0',
            'promotion_bonus' => 'integer|min:0',
            'max_participants_per_region' => 'integer|min:0',
            'points' => 'array',
            'points.*.position' => 'required|integer|min:1',
            'points.*.points' => 'required|integer|min:0',
            'points.*.min_teams' => 'nullable|integer|min:1',
            'points.*.max_teams' => 'nullable|integer|min:1',
            'points.*.description' => 'nullable|string|max:500'
        ]);

        try {
            $tournamentType = TournamentType::create($request->only([
                'code', 'name', 'color_class', 'is_active', 'sort_order', 'ignore_teams_multiplier', 'coefficient', 'participation_points', 'promotion_bonus', 'max_participants_per_region'
            ]));

            // Создаем точки для турнира
            if ($request->has('points')) {
                foreach ($request->points as $pointData) {
                    $tournamentType->points()->create($pointData);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Тип турнира успешно создан',
                'data' => $tournamentType->load('points')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании типа турнира: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить тип турнира
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tournamentType = TournamentType::findOrFail($id);

        $request->validate([
            'code' => 'string|max:50|unique:tournament_types,code,' . $id,
            'name' => 'string|max:255',
            'color_class' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'ignore_teams_multiplier' => 'boolean',
            'coefficient' => 'numeric|min:0.1|max:2.0',
            'participation_points' => 'integer|min:0',
            'promotion_bonus' => 'integer|min:0',
            'max_participants_per_region' => 'integer|min:0',
            'points' => 'array',
            'points.*.position' => 'required|integer|min:1',
            'points.*.points' => 'required|integer|min:0',
            'points.*.min_teams' => 'nullable|integer|min:1',
            'points.*.max_teams' => 'nullable|integer|min:1',
            'points.*.description' => 'nullable|string|max:500'
        ]);

        try {
            // Логируем значение до обновления
            Log::info('Перед обновлением', ['max_participants_per_region' => $request->max_participants_per_region]);

            $tournamentType->update($request->only([
                'code', 'name', 'color_class', 'is_active', 'sort_order', 'ignore_teams_multiplier', 'coefficient', 'participation_points', 'promotion_bonus', 'max_participants_per_region'
            ]));

            // Логируем значение после обновления
            Log::info('После обновления', ['max_participants_per_region' => $tournamentType->max_participants_per_region]);

            // Обновляем точки для турнира
            if ($request->has('points')) {
                // Удаляем старые точки
                $tournamentType->points()->delete();

                // Создаем новые точки
                foreach ($request->points as $pointData) {
                    $tournamentType->points()->create($pointData);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Тип турнира успешно обновлен',
                'data' => $tournamentType->load('points')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении типа турнира: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить тип турнира
     */
    public function destroy(int $id): JsonResponse
    {
        $tournamentType = TournamentType::findOrFail($id);

        // Проверяем, есть ли достижения с этим типом турнира
        if ($tournamentType->clubAchievements()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить тип турнира, который используется в достижениях клубов'
            ], 422);
        }

        try {
            $tournamentType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Тип турнира успешно удален'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении типа турнира: ' . $e->getMessage()
            ], 500);
        }
    }
}
