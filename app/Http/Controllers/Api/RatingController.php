<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RatingActualityStatus;
use App\Models\RatingRegion;
use App\Models\RatingYear;
use App\Models\RegionRating;
use App\Models\Sport;
use App\Services\SRRRRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    protected SRRRRatingService $ratingService;

    public function __construct(SRRRRatingService $ratingService)
    {
        $this->ratingService = $ratingService;
    }

    /**
     * Получить топ рейтинга по виду спорта
     */
    public function getTopRating(Request $request): JsonResponse
    {
        $request->validate([
            'sport_id' => 'required|integer|exists:sports,id',
            'year' => 'required|integer|min:2020|max:2030',
            'limit' => 'integer|min:1|max:100'
        ]);

        $topRating = $this->ratingService->getTopRating(
            $request->sport_id,
            $request->year,
            $request->get('limit', 10)
        );

        return response()->json([
            'success' => true,
            'data' => $topRating
        ]);
    }

    /**
     * Получить рейтинг конкретного региона
     */
    public function getRegionRating(Request $request): JsonResponse
    {
        $request->validate([
            'region_id' => 'required|integer|exists:rating_regions,id',
            'sport_id' => 'required|integer|exists:sports,id',
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        $rating = RegionRating::with(['region', 'sport'])
            ->where('rating_region_id', $request->region_id)
            ->where('sport_id', $request->sport_id)
            ->where('year', $request->year)
            ->first();

        if (!$rating) {
            return response()->json([
                'success' => false,
                'message' => 'Рейтинг не найден'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rating
        ]);
    }

    /**
     * Получить динамику рейтинга региона
     */
    public function getRegionDynamics(Request $request): JsonResponse
    {
        $request->validate([
            'region_id' => 'required|integer|exists:rating_regions,id',
            'sport_id' => 'required|integer|exists:sports,id',
            'start_year' => 'required|integer|min:2020|max:2030',
            'end_year' => 'required|integer|min:2020|max:2030|gte:start_year'
        ]);

        $dynamics = $this->ratingService->getRegionDynamics(
            $request->region_id,
            $request->sport_id,
            $request->start_year,
            $request->end_year
        );

        return response()->json([
            'success' => true,
            'data' => $dynamics
        ]);
    }

    /**
     * Получить общую статистику по регионам
     */
    public function getRegionsStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        $statistics = $this->ratingService->getRegionsStatistics($request->year);

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * Получить детали расчета рейтинга
     */
    public function getCalculationDetails(Request $request): JsonResponse
    {
        $request->validate([
            'region_id' => 'required|integer|exists:rating_regions,id',
            'sport_id' => 'required|integer|exists:sports,id',
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        $details = $this->ratingService->getRegionCalculationDetails(
            $request->region_id,
            $request->sport_id,
            $request->year
        );

        if (empty($details)) {
            return response()->json([
                'success' => false,
                'message' => 'Детали расчета не найдены'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $details
        ]);
    }

        /**
     * Получить список регионов
     */
    public function getRegions(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $query = $request->get('q', '');

        $regions = RatingRegion::orderBy('name')
            ->withCount('clubs');

        if ($query) {
            $regions->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }

        $regions = $regions->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

        /**
     * Получить список видов спорта для рейтинга
     */
    public function getSports(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $query = $request->get('q', '');

        $sports = Sport::orderBy('title');

        if ($query) {
            $sports->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('title_short', 'like', "%{$query}%");
            });
        }

        $sports = $sports->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $sports
        ]);
    }

        /**
     * Получить список годов для рейтинга
     */
    public function getYears(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $query = $request->get('q', '');
        $year = $request->get('year', '');
        $isActive = $request->get('is_active', '');

        $years = RatingYear::orderBy('year', 'desc')
            ->withCount('achievements');

        if ($query) {
            $years->where(function($q) use ($query) {
                $q->where('year', 'like', "%{$query}%")
                  ->orWhere('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }

        if ($year) {
            $years->where('year', $year);
        }

        if ($isActive !== '') {
            $years->where('is_active', $isActive);
        }

        $years = $years->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }

    /**
     * Создать новый год рейтинга
     */
    public function storeYear(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030|unique:rating_years,year',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        try {
            $year = RatingYear::create([
                'year' => $request->year,
                'title' => $request->title,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Год успешно создан',
                'data' => $year
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании года: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить год рейтинга
     */
    public function updateYear(Request $request, int $id): JsonResponse
    {
        $year = RatingYear::find($id);

        if (!$year) {
            return response()->json([
                'success' => false,
                'message' => 'Год не найден'
            ], 404);
        }

        $request->validate([
            'year' => 'required|integer|min:2020|max:2030|unique:rating_years,year,' . $id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        try {
            $year->update([
                'year' => $request->year,
                'title' => $request->title,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Год успешно обновлен',
                'data' => $year
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении года: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить год рейтинга
     */
    public function destroyYear(int $id): JsonResponse
    {
        $year = RatingYear::find($id);

        if (!$year) {
            return response()->json([
                'success' => false,
                'message' => 'Год не найден'
            ], 404);
        }

        // Проверяем, есть ли связанные записи
        $hasRelatedData = $year->achievements()->exists() || $year->ratings()->exists();

        if ($hasRelatedData) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить год, у которого есть связанные данные (достижения или рейтинги)'
            ], 400);
        }

        try {
            $year->delete();

            return response()->json([
                'success' => true,
                'message' => 'Год успешно удален'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении года: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Пересчитать рейтинги для всех лет (только для админов)
     */
    public function calculateYearlyRating(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year');
            if ($year) {
                $this->ratingService->calculateYearlyRating($year);
            } else {
                $this->ratingService->calculateAllYearsRatings();
            }
            return response()->json([
                'success' => true,
                'message' => 'Рейтинги успешно пересчитаны'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при расчете рейтинга: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создать новый регион рейтинга
     */
    public function storeRegion(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:rating_regions,code',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        try {
            $region = RatingRegion::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Регион успешно создан',
                'data' => $region
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании региона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить регион рейтинга
     */
    public function updateRegion(Request $request, int $id): JsonResponse
    {
        $region = RatingRegion::find($id);

        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => 'Регион не найден'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:rating_regions,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        try {
            $region->update([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Регион успешно обновлен',
                'data' => $region
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении региона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить регион рейтинга
     */
    public function destroyRegion(int $id): JsonResponse
    {
        $region = RatingRegion::find($id);

        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => 'Регион не найден'
            ], 404);
        }

        // Проверяем, есть ли связанные записи
        $hasRelatedData = $region->ratings()->exists() || $region->clubs()->exists();

        if ($hasRelatedData) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить регион, у которого есть связанные данные (рейтинги или клубы)'
            ], 400);
        }

        try {
            $region->delete();

            return response()->json([
                'success' => true,
                'message' => 'Регион успешно удален'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении региона: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить итоговые рейтинги регионов за выбранный год с деталями по командам и предыдущим годам
     */
    public function getRegionYearTotalRatings(Request $request): \Illuminate\Http\JsonResponse
    {
        $year = $request->get('year');
        $ratingYear = \App\Models\RatingYear::where('year', $year)->first();
        if (!$ratingYear) {
            return response()->json([]);
        }
        $regions = \App\Models\RatingRegion::all();
        $result = [];
        foreach ($regions as $region) {
            $rating = \App\Models\RegionYearTotalRating::with('region')
                ->where('rating_region_id', $region->id)
                ->where('rating_year_id', $ratingYear->id)
                ->first();
            // Список команд, принёсших очки в этом году
            $teams = \App\Models\ClubAchievement::with(['club', 'tournamentType'])
                ->whereHas('club', function ($q) use ($region) {
                    $q->where('rating_region_id', $region->id);
                })
                ->where('year', $year)
                ->get()
                ->map(function($ach) {
                    return [
                        'club_id' => $ach->club_id,
                        'club_name' => $ach->club ? $ach->club->name : null,
                        'points' => $ach->points_earned,
                        'logo_url' => $ach->club ? $ach->club->logo_url : null,
                        'tournament_type' => $ach->tournamentType ? $ach->tournamentType->name : $ach->tournament_type,
                        'position' => $ach->position
                    ];
                });
            // Значения рейтинга за предыдущие 3 года
            $prev_years = [];
            for ($i = 1; $i <= 3; $i++) {
                $prevYear = \App\Models\RatingYear::where('year', $year - $i)->first();
                if ($prevYear) {
                    $prevRating = \App\Models\RegionYearTotalRating::where('rating_region_id', $region->id)
                        ->where('rating_year_id', $prevYear->id)
                        ->first();
                    $prev_years[] = [
                        'year' => $year - $i,
                        'rating' => $prevRating ? $prevRating->rating : 0,
                        'yearly_rating' => $prevRating ? $prevRating->yearly_rating : 0
                    ];
                } else {
                    $prev_years[] = [
                        'year' => $year - $i,
                        'rating' => 0,
                        'yearly_rating' => 0
                    ];
                }
            }
            $result[] = [
                'region' => $region->name,
                'region_id' => $region->id,
                'year' => $year,
                'rating' => $rating ? $rating->rating : 0,
                'yearly_rating' => $rating ? $rating->yearly_rating : 0,
                'teams' => $teams,
                'prev_years' => $prev_years
            ];
        }
        return response()->json($result);
    }

    /**
     * Получить историю итоговых рейтингов регионов за последние 4 года
     */
    public function getRegionYearTotalRatingsHistory(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $years = \App\Models\RatingYear::orderByDesc('year')->limit(4)->get();
            $regions = \App\Models\RatingRegion::all();
            $result = [];
            foreach ($regions as $region) {
                $row = [
                    'region' => $region->name,
                    'region_id' => $region->id,
                    'ratings' => []
                ];
                foreach ($years as $year) {
                    $rating = \App\Models\RegionYearTotalRating::where('rating_region_id', $region->id)
                        ->where('rating_year_id', $year->id)
                        ->first();
                    $place = null;
                    if ($rating) {
                        $place = \App\Models\RegionYearTotalRating::where('rating_year_id', $year->id)
                            ->where('rating', '>=', $rating->rating)
                            ->count();
                    }
                    $row['ratings'][] = [
                        'year' => $year->year,
                        'rating' => $rating ? $rating->rating : 0,
                        'yearly_rating' => $rating ? $rating->yearly_rating : 0,
                        'place' => $place
                    ];
                }
                $result[] = $row;
            }
            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('getRegionYearTotalRatingsHistory error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([], 500);
        }
    }

    /**
     * Получить статус актуальности рейтинга (глобально)
     */
    public function getRatingActualityStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $status = RatingActualityStatus::first();
        return response()->json([
            'is_actual' => $status ? (bool)$status->is_actual : true
        ]);
    }

    /**
     * Сбросить статус актуальности рейтинга (использовать при изменении достижений)
     */
    public static function setRatingNotActual()
    {
        RatingActualityStatus::updateOrCreate(
            ['id' => 1],
            ['is_actual' => false]
        );
    }

    /**
     * Установить статус актуальности рейтинга (использовать после пересчёта)
     */
    public static function setRatingActual()
    {
        RatingActualityStatus::updateOrCreate(
            ['id' => 1],
            ['is_actual' => true]
        );
    }
}
