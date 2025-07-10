<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatingRegion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Клубы в этом регионе
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    /**
     * Рейтинги региона
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(RegionRating::class);
    }

    /**
     * Получить рейтинг региона по спорту и году
     */
    public function getRating(int $sportId, int $year): ?RegionRating
    {
        return $this->ratings()
            ->where('sport_id', $sportId)
            ->where('year', $year)
            ->first();
    }

    /**
     * Получить общий рейтинг региона за год (по всем видам спорта)
     */
    public function getTotalRating(int $year): float
    {
        return $this->ratings()
            ->where('year', $year)
            ->sum('total_points');
    }

    public function totalYearRatings()
    {
        return $this->hasMany(RegionYearTotalRating::class, 'rating_region_id');
    }
}
