<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Controller;
use App\Models\ClubAchievement;
use App\Services\SRRRRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClubAchievementController extends Controller
{
    protected SRRRRatingService $ratingService;

    public function __construct(SRRRRatingService $ratingService)
    {
        $this->ratingService = $ratingService;
    }

    /**
     * Получить достижения клубов
     */
    public function getClubAchievements(Request $request): JsonResponse
    {
        $request->validate([
            'club_id' => 'nullable|integer|exists:clubs,id',
            'year' => 'nullable|integer|min:2020|max:2030',
            'tournament_type' => 'nullable|string',
            'tournament_type_id' => 'nullable|integer|exists:tournament_types,id',
            'region_id' => 'nullable|integer|exists:rating_regions,id'
        ]);

        $query = ClubAchievement::with(['club.ratingRegion', 'tournamentType.points']);

        // Фильтр по клубу
        if ($request->has('club_id') && $request->club_id) {
            $query->where('club_id', $request->club_id);
        }

        // Фильтр по году
        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        // Фильтр по типу турнира
        if ($request->has('tournament_type') && $request->tournament_type) {
            $query->where('tournament_type', $request->tournament_type);
        }

        // Фильтр по ID типа турнира
        if ($request->has('tournament_type_id') && $request->tournament_type_id) {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        // Фильтр по региону
        if ($request->has('region_id') && $request->region_id) {
            $query->whereHas('club', function ($q) use ($request) {
                $q->where('rating_region_id', $request->region_id);
            });
        }

        $achievements = $query->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50); // Пагинация по 50 записей

        // Добавляем вычисленные поля для отображения
        $achievements->getCollection()->transform(function ($achievement) {
            try {
                // Вычисляем базовые очки за место (без множителей и бонусов)
                $achievement->base_points = $this->calculateBasePoints($achievement);

                // Вычисляем итоговые очки для отображения
                $achievement->calculated_points = $this->calculateDisplayPoints($achievement);

                // Вычисляем коэффициент команд для отображения
                $achievement->teams_multiplier = $this->calculateTeamsMultiplier($achievement);
            } catch (\Exception $e) {
                Log::error('Ошибка обработки достижения', [
                    'achievement_id' => $achievement->id,
                    'error' => $e->getMessage()
                ]);
                $achievement->base_points = 0;
                $achievement->calculated_points = 0;
                $achievement->teams_multiplier = null;
            }
            return $achievement;
        });

        // Отладочная информация
        if ($achievements->count() > 0) {
            $firstAchievement = $achievements->first();
            Log::info('Пример достижения из API:', [
                'id' => $firstAchievement->id,
                'club_id' => $firstAchievement->club_id,
                'club_rating_region' => $firstAchievement->club?->rating_region,
                'club_rating_region_id' => $firstAchievement->club?->rating_region_id,
                'base_points' => $firstAchievement->base_points,
                'calculated_points' => $firstAchievement->calculated_points,
                'teams_multiplier' => $firstAchievement->teams_multiplier,
                'tournament_type' => $firstAchievement->tournamentType?->name
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $achievements
        ]);
    }

    /**
     * Добавить достижение клуба
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'club_id' => 'required|integer|exists:clubs,id',
            'year' => 'required|integer|min:2020|max:2030',
            'tournament_type_id' => 'required|integer|exists:tournament_types,id',
            'position' => 'required|integer|min:1',
            'teams_count' => 'required|integer|min:1',
            'promoted' => 'boolean',
            'is_farm' => 'boolean'
        ]);

        // Проверяем, есть ли у клуба регион рейтинга
        $club = \App\Models\Club::find($request->club_id);
        if (!$club->rating_region_id) {
            return response()->json([
                'success' => false,
                'message' => 'У выбранного клуба не указан регион рейтинга.',
                'code' => 'CLUB_NO_REGION',
                'club_id' => $club->id,
                'club_name' => $club->title
            ], 422);
        }

        try {
            // Фильтруем только нужные поля
            $data = $request->only([
                'club_id',
                'year',
                'tournament_type_id',
                'position',
                'teams_count',
                'promoted',
                'is_farm'
            ]);

            Log::info('Данные для создания достижения', $data);

            // Автоматически заполняем tournament_type из tournament_type_id
            $tournamentType = \App\Models\TournamentType::find($data['tournament_type_id']);
            if ($tournamentType) {
                $data['tournament_type'] = $tournamentType->code;
            }

            $achievement = $this->ratingService->addClubAchievement($data);
            // Сбросить актуальность рейтинга для года
            RatingController::setRatingNotActual();

            return response()->json([
                'success' => true,
                'message' => 'Достижение успешно добавлено',
                'data' => $achievement->load(['club'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при добавлении достижения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить достижение клуба
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $achievement = ClubAchievement::findOrFail($id);

        $request->validate([
            'club_id' => 'integer|exists:clubs,id',
            'year' => 'integer|min:2020|max:2030',
            'tournament_type_id' => 'integer|exists:tournament_types,id',
            'position' => 'integer|min:1',
            'teams_count' => 'integer|min:1',
            'promoted' => 'boolean',
            'is_farm' => 'boolean'
        ]);

        // Проверяем, есть ли у клуба регион рейтинга (если клуб изменяется)
        if ($request->has('club_id') && $request->club_id != $achievement->club_id) {
            $club = \App\Models\Club::find($request->club_id);
            if (!$club->rating_region_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'У выбранного клуба не указан регион рейтинга. Пожалуйста, сначала добавьте регион к клубу в разделе "Клубы".',
                    'errors' => [
                        'club_id' => ['У клуба не указан регион рейтинга']
                    ]
                ], 422);
            }
        }

        try {
            // Фильтруем только нужные поля
            $data = $request->only([
                'club_id',
                'year',
                'tournament_type_id',
                'position',
                'teams_count',
                'promoted',
                'is_farm'
            ]);

            // Автоматически заполняем tournament_type из tournament_type_id
            if (isset($data['tournament_type_id'])) {
                $tournamentType = \App\Models\TournamentType::find($data['tournament_type_id']);
                if ($tournamentType) {
                    $data['tournament_type'] = $tournamentType->code;
                }
            }

            $achievement->update($data);
            $achievement->calculatePoints();
            // Сбросить актуальность рейтинга для года
            RatingController::setRatingNotActual();

            // Пересчитать рейтинг региона
            if ($achievement->club->rating_region_id && $achievement->club->sport_id) {
                $this->ratingService->calculateRegionSportRating(
                    $achievement->club->ratingRegion,
                    $achievement->club->sport,
                    $achievement->year
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Достижение успешно обновлено',
                'data' => $achievement->load(['club'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении достижения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Вычислить базовые очки за место (без множителей и бонусов)
     */
    private function calculateBasePoints(ClubAchievement $achievement): int
    {
        if (!$achievement->tournamentType) {
            return 0;
        }

        try {
            // Получаем базовые очки за место
            $basePoints = $achievement->tournamentType->getPointsForPosition($achievement->position, $achievement->teams_count);

            // Если нет очков за место, но есть очки за участие, используем их
            if ($basePoints === 0 && $achievement->tournamentType->participation_points > 0) {
                $basePoints = $achievement->tournamentType->participation_points;
            }

            return (int) $basePoints;
        } catch (\Exception $e) {
            Log::error('Ошибка вычисления базовых очков для достижения', [
                'achievement_id' => $achievement->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Вычислить итоговые очки для отображения (с множителями и бонусами)
     */
    private function calculateDisplayPoints(ClubAchievement $achievement): int
    {
        if (!$achievement->tournamentType) {
            return 0;
        }

        try {
            // Получаем базовые очки за место
            $basePoints = $achievement->tournamentType->getPointsForPosition($achievement->position, $achievement->teams_count);

            // Если нет очков за место, но есть очки за участие, используем их
            if ($basePoints === 0 && $achievement->tournamentType->participation_points > 0) {
                $basePoints = $achievement->tournamentType->participation_points;
            }

            // Применяем множитель по количеству команд, если не установлен флаг ignore_teams_multiplier
            if (!$achievement->tournamentType->ignore_teams_multiplier) {
                $multiplier = $achievement->teams_count / 10;
                $basePoints = $basePoints * $multiplier;
            }

            // Для первой лиги добавляем бонус за повышение
            if ($achievement->tournamentType->code === 'first_league' && $achievement->promoted) {
                $promotionBonus = $achievement->tournamentType->promotion_bonus ?? 30;
                if (!$achievement->tournamentType->ignore_teams_multiplier) {
                    $multiplier = $achievement->teams_count / 10;
                    $promotionBonus = $promotionBonus * $multiplier;
                }
                $basePoints += $promotionBonus;
            }

            // Применяем коэффициент
            $coefficient = $achievement->is_farm
                ? ($achievement->tournamentType->coefficient ?? 0.5)
                : 1.0;
            $basePoints = $basePoints * $coefficient;

            return (int) round($basePoints);
        } catch (\Exception $e) {
            Log::error('Ошибка вычисления итоговых очков для достижения', [
                'achievement_id' => $achievement->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Вычислить коэффициент команд для отображения
     */
    private function calculateTeamsMultiplier(ClubAchievement $achievement): ?float
    {
        try {
            if (!$achievement->tournamentType || $achievement->tournamentType->ignore_teams_multiplier) {
                return null;
            }

            return round($achievement->teams_count / 10, 1);
        } catch (\Exception $e) {
            Log::error('Ошибка вычисления коэффициента команд для достижения', [
                'achievement_id' => $achievement->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Удалить достижение клуба
     */
    public function destroy(int $id): JsonResponse
    {
        $achievement = ClubAchievement::findOrFail($id);

        try {
            $club = $achievement->club;
            $year = $achievement->year;

            $achievement->delete();
            // Сбросить актуальность рейтинга для года
            RatingController::setRatingNotActual();

            // Пересчитать рейтинг региона
            if ($club->rating_region_id && $club->sport_id) {
                $this->ratingService->calculateRegionSportRating(
                    $club->ratingRegion,
                    $club->sport,
                    $year
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Достижение успешно удалено'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении достижения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику достижений по регионам
     */
    public function getAchievementsStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'tournament_type' => 'nullable|in:championship,first_league,cup,supercup'
        ]);

        $query = ClubAchievement::with(['club.ratingRegion', 'club.sport'])
            ->where('year', $request->year);

        if ($request->has('tournament_type')) {
            $query->where('tournament_type', $request->tournament_type);
        }

        $statistics = $query->get()
            ->groupBy('club.ratingRegion.name')
            ->map(function ($achievements, $regionName) {
                return [
                    'region' => $regionName,
                    'total_points' => $achievements->sum('points_earned'),
                    'achievements_count' => $achievements->count(),
                    'tournaments' => $achievements->groupBy('tournament_type')->map->count()
                ];
            })
            ->sortByDesc('total_points')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * Массовый пересчёт очков для всех достижений клубов
     */
    public function recalculatePoints(Request $request): JsonResponse
    {
        // Можно добавить проверку прав, если нужно
        $count = 0;
        $errors = [];
        try {
            $achievements = ClubAchievement::with(['tournamentType'])->get();
            foreach ($achievements as $achievement) {
                try {
                    $achievement->calculatePoints();
                    $count++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'id' => $achievement->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
            // После пересчёта можно сбросить актуальность рейтинга, если требуется
            RatingController::setRatingNotActual();
            return response()->json([
                'success' => true,
                'message' => "Пересчитано достижений: $count",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при массовом пересчёте очков: ' . $e->getMessage()
            ], 500);
        }
    }
}
