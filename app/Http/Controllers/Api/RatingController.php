<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RatingRegion;
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
    public function getRegions(): JsonResponse
    {
        $regions = RatingRegion::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regions
        ]);
    }

    /**
     * Получить список видов спорта для рейтинга
     */
    public function getSports(): JsonResponse
    {
        $sports = Sport::orderBy('title')->get();

        return response()->json([
            'success' => true,
            'data' => $sports
        ]);
    }

    /**
     * Рассчитать рейтинг за год (только для админов)
     */
    public function calculateYearlyRating(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        try {
            $this->ratingService->calculateYearlyRating($request->year);

            return response()->json([
                'success' => true,
                'message' => "Рейтинг за {$request->year} год успешно рассчитан"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при расчете рейтинга: ' . $e->getMessage()
            ], 500);
        }
    }
}
