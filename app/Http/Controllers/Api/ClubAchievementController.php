<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubAchievement;
use App\Services\SRRRRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $query = ClubAchievement::with(['club.ratingRegion', 'tournamentType']);

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
            ->get();

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
            'tournament_type' => 'nullable|string',
            'tournament_type_id' => 'required|integer|exists:tournament_types,id',
            'position' => 'required|integer|min:1',
            'teams_count' => 'required|integer|min:1',
            'promoted' => 'boolean',
            'coefficient' => 'numeric|min:0.1|max:2.0'
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
                'tournament_type',
                'tournament_type_id',
                'position',
                'teams_count',
                'promoted',
                'coefficient'
            ]);

            $achievement = $this->ratingService->addClubAchievement($data);

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
            'tournament_type' => 'nullable|string',
            'tournament_type_id' => 'integer|exists:tournament_types,id',
            'position' => 'integer|min:1',
            'teams_count' => 'integer|min:1',
            'promoted' => 'boolean',
            'coefficient' => 'numeric|min:0.1|max:2.0'
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
                'tournament_type',
                'tournament_type_id',
                'position',
                'teams_count',
                'promoted',
                'coefficient'
            ]);

            $achievement->update($data);
            $achievement->calculatePoints();

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
     * Удалить достижение клуба
     */
    public function destroy(int $id): JsonResponse
    {
        $achievement = ClubAchievement::findOrFail($id);

        try {
            $club = $achievement->club;
            $year = $achievement->year;

            $achievement->delete();

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
}
