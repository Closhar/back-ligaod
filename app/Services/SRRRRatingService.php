<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubAchievement;
use App\Models\RatingRegion;
use App\Models\RegionRating;
use App\Models\Sport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SRRRRatingService
{
    /**
     * Рассчитать рейтинг для всех регионов за указанный год
     */
    public function calculateYearlyRating(int $year): void
    {
        $regions = RatingRegion::where('is_active', true)->get();
        $sports = Sport::all();

        foreach ($regions as $region) {
            foreach ($sports as $sport) {
                $this->calculateRegionSportRating($region, $sport, $year);
            }
        }

        // Обновить ранги после расчета всех рейтингов
        $this->updateRanks($year);

        // Обновить итоговые рейтинги регионов по годам
        $this->updateRegionYearTotalRatings($year);
    }

    /**
     * Рассчитать рейтинг для конкретного региона и вида спорта
     */
    public function calculateRegionSportRating(RatingRegion $region, Sport $sport, int $year): void
    {
        // Получить или создать запись рейтинга
        $rating = RegionRating::firstOrCreate([
            'rating_region_id' => $region->id,
            'sport_id' => $sport->id,
            'year' => $year
        ]);

        // Рассчитать рейтинг
        $rating->calculate();
    }

    /**
     * Обновить ранги всех регионов за год
     */
    public function updateRanks(int $year): void
    {
        $sports = Sport::all();

        foreach ($sports as $sport) {
            $rankings = RegionRating::where('sport_id', $sport->id)
                ->where('year', $year)
                ->orderBy('total_points', 'desc')
                ->get();

            $rank = 1;
            foreach ($rankings as $ranking) {
                $ranking->update(['rank' => $rank]);
                $rank++;
            }
        }
    }

    /**
     * Обновить/создать итоговые рейтинги регионов по годам
     */
    public function updateRegionYearTotalRatings(int $year): void
    {
        $regions = RatingRegion::where('is_active', true)->get();
        $ratingYear = \App\Models\RatingYear::where('year', $year)->first();
        if (!$ratingYear) return;

        foreach ($regions as $region) {
            // Получаем рейтинги за текущий и три предыдущих года
            $years = [$year-3, $year-2, $year-1, $year];
            $ratingYears = \App\Models\RatingYear::whereIn('year', $years)->pluck('id', 'year');
            $total = 0;
            foreach ($years as $y) {
                $yearId = $ratingYears[$y] ?? null;
                if ($yearId) {
                    $sum = \App\Models\RegionRating::where('rating_region_id', $region->id)
                        ->where('year', $y)
                        ->sum('total_points');
                    $total += $sum;
                }
            }
            // Обновляем или создаём запись
            \App\Models\RegionYearTotalRating::updateOrCreate(
                [
                    'rating_region_id' => $region->id,
                    'rating_year_id' => $ratingYear->id,
                ],
                [
                    'rating' => $total
                ]
            );
        }
    }

    /**
     * Добавить достижение клуба
     */
    public function addClubAchievement(array $data): ClubAchievement
    {
        Log::info('Создание достижения клуба', $data);

        $achievement = ClubAchievement::create($data);
        Log::info('Достижение создано', ['id' => $achievement->id, 'points_earned' => $achievement->points_earned]);

        $achievement->calculatePoints();
        Log::info('Очки рассчитаны', ['id' => $achievement->id, 'points_earned' => $achievement->points_earned]);

        // Пересчитать рейтинг региона
        if ($achievement->club->rating_region_id && $achievement->club->sport_id) {
            $this->calculateRegionSportRating(
                $achievement->club->ratingRegion,
                $achievement->club->sport,
                $achievement->year
            );
        }

        return $achievement;
    }

    /**
     * Получить топ рейтинга по виду спорта и году
     */
    public function getTopRating(int $sportId, int $year, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return RegionRating::with(['region', 'sport'])
            ->where('sport_id', $sportId)
            ->where('year', $year)
            ->orderBy('total_points', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Получить динамику рейтинга региона
     */
    public function getRegionDynamics(int $regionId, int $sportId, int $startYear, int $endYear): array
    {
        $ratings = RegionRating::where('rating_region_id', $regionId)
            ->where('sport_id', $sportId)
            ->whereBetween('year', [$startYear, $endYear])
            ->orderBy('year')
            ->get();

        $dynamics = [];
        foreach ($ratings as $rating) {
            $dynamics[] = [
                'year' => $rating->year,
                'points' => $rating->total_points,
                'rank' => $rating->rank
            ];
        }

        return $dynamics;
    }

    /**
     * Получить общую статистику по регионам
     */
    public function getRegionsStatistics(int $year): array
    {
        $stats = DB::table('region_ratings')
            ->join('rating_regions', 'region_ratings.rating_region_id', '=', 'rating_regions.id')
            ->join('sports', 'region_ratings.sport_id', '=', 'sports.id')
            ->where('region_ratings.year', $year)
            ->select(
                'rating_regions.name as region_name',
                'sports.title as sport_title',
                'region_ratings.total_points',
                'region_ratings.rank'
            )
            ->orderBy('sports.title')
            ->orderBy('region_ratings.total_points', 'desc')
            ->get();

        return $stats->toArray();
    }

    /**
     * Получить детали расчета рейтинга региона
     */
    public function getRegionCalculationDetails(int $regionId, int $sportId, int $year): array
    {
        $rating = RegionRating::where('rating_region_id', $regionId)
            ->where('sport_id', $sportId)
            ->where('year', $year)
            ->first();

        if (!$rating) {
            return [];
        }

        $achievements = ClubAchievement::with(['club'])
            ->whereHas('club', function ($query) use ($regionId, $sportId) {
                $query->where('rating_region_id', $regionId)
                      ->where('sport_id', $sportId);
            })
            ->where('year', $year)
            ->get();

        return [
            'rating' => $rating,
            'achievements' => $achievements,
            'calculation_details' => $rating->details
        ];
    }
}
