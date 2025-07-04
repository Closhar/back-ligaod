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
     * Получить достижения клуба
     */
    public function getClubAchievements(Request $request): JsonResponse
    {
        $request->validate([
            'club_id' => 'required|integer|exists:clubs,id',
            'year' => 'integer|min:2020|max:2030'
        ]);

        $query = ClubAchievement::with(['club', 'competition'])
            ->where('club_id', $request->club_id);

        if ($request->has('year')) {
            $query->where('year', $request->year);
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
            'competition_id' => 'required|integer|exists:competitions,id',
            'year' => 'required|integer|min:2020|max:2030',
            'tournament_type' => 'required|in:championship,first_league,cup,supercup',
            'division' => 'nullable|in:premier,first',
            'position' => 'required|integer|min:1',
            'teams_count' => 'required|integer|min:1',
            'promoted' => 'boolean',
            'coefficient' => 'numeric|min:0.1|max:2.0'
        ]);

        try {
            $achievement = $this->ratingService->addClubAchievement($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Достижение успешно добавлено',
                'data' => $achievement->load(['club', 'competition'])
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
            'competition_id' => 'integer|exists:competitions,id',
            'year' => 'integer|min:2020|max:2030',
            'tournament_type' => 'in:championship,first_league,cup,supercup',
            'division' => 'nullable|in:premier,first',
            'position' => 'integer|min:1',
            'teams_count' => 'integer|min:1',
            'promoted' => 'boolean',
            'coefficient' => 'numeric|min:0.1|max:2.0'
        ]);

        try {
            $achievement->update($request->all());
            $achievement->calculatePoints();

            // Пересчитать рейтинг региона
            if ($achievement->club->rating_region_id) {
                $this->ratingService->calculateRegionSportRating(
                    $achievement->club->ratingRegion,
                    $achievement->competition->sport,
                    $achievement->year
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Достижение успешно обновлено',
                'data' => $achievement->load(['club', 'competition'])
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
            $competition = $achievement->competition;
            $year = $achievement->year;

            $achievement->delete();

            // Пересчитать рейтинг региона
            if ($club->rating_region_id) {
                $this->ratingService->calculateRegionSportRating(
                    $club->ratingRegion,
                    $competition->sport,
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

        $query = ClubAchievement::with(['club.ratingRegion', 'competition.sport'])
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
