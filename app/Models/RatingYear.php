<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatingYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'title',
        'description',
        'is_active',
        'is_calculated',
        'calculated_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_calculated' => 'boolean',
        'calculated_at' => 'datetime'
    ];

    /**
     * Рейтинги за этот год
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(RegionRating::class, 'year', 'year');
    }

    /**
     * Достижения клубов за этот год
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(ClubAchievement::class, 'year', 'year');
    }

    /**
     * Получить заголовок года
     */
    public function getTitleAttribute($value): string
    {
        return $value ?: $this->year . ' год';
    }

    /**
     * Получить активные годы
     */
    public static function getActiveYears()
    {
        return self::where('is_active', true)
            ->orderBy('year', 'desc')
            ->get();
    }

    /**
     * Создать или обновить год
     */
    public static function createOrUpdateYear(int $year, array $data = []): self
    {
        return self::updateOrCreate(
            ['year' => $year],
            array_merge($data, [
                'title' => $data['title'] ?? $year . ' год'
            ])
        );
    }

    public function totalRegionRatings()
    {
        return $this->hasMany(RegionYearTotalRating::class, 'rating_year_id');
    }
}
