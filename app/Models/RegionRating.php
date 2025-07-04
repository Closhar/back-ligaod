<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'rating_region_id',
        'sport_id',
        'year',
        'total_points',
        'rank',
        'details',
        'calculated_at'
    ];

    protected $casts = [
        'total_points' => 'decimal:2',
        'details' => 'array',
        'calculated_at' => 'datetime'
    ];

    /**
     * Регион рейтинга
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(RatingRegion::class, 'rating_region_id');
    }

    /**
     * Вид спорта
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Получить достижения клубов региона за год
     */
    public function getClubAchievements(): \Illuminate\Database\Eloquent\Collection
    {
        return ClubAchievement::whereHas('club', function ($query) {
            $query->where('rating_region_id', $this->rating_region_id)
                  ->where('sport_id', $this->sport_id);
        })
        ->where('year', $this->year)
        ->get();
    }

    /**
     * Рассчитать рейтинг региона
     */
    public function calculate(): void
    {
        $achievements = $this->getClubAchievements();
        $totalPoints = 0;
        $details = [];

        foreach ($achievements as $achievement) {
            $points = $achievement->points_earned * $achievement->coefficient;
            $totalPoints += $points;

            $details[] = [
                'club_id' => $achievement->club_id,
                'club_name' => $achievement->club->name,
                'tournament_type' => $achievement->tournament_type,
                'position' => $achievement->position,
                'teams_count' => $achievement->teams_count,
                'promoted' => $achievement->promoted,
                'coefficient' => $achievement->coefficient,
                'points_earned' => $achievement->points_earned,
                'final_points' => $points
            ];
        }

        $this->update([
            'total_points' => $totalPoints,
            'details' => $details,
            'calculated_at' => now()
        ]);
    }
}
