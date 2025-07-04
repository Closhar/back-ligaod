<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'color_class',
        'is_active',
        'sort_order',
        'ignore_teams_multiplier',
        'coefficient',
        'participation_points',
        'promotion_bonus'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'ignore_teams_multiplier' => 'boolean',
        'coefficient' => 'decimal:2',
        'participation_points' => 'integer',
        'promotion_bonus' => 'integer'
    ];

    /**
     * Точки за места в турнире
     */
    public function points(): HasMany
    {
        return $this->hasMany(TournamentTypePoint::class);
    }

    /**
     * Достижения клубов этого типа турнира
     */
    public function clubAchievements(): HasMany
    {
        return $this->hasMany(ClubAchievement::class);
    }

    /**
     * Получить очки за определенное место и количество команд
     */
    public function getPointsForPosition(int $position, int $teamsCount): int
    {
        $point = $this->points()
            ->where('position', $position)
            ->where('is_active', true)
            ->where(function ($query) use ($teamsCount) {
                $query->whereNull('min_teams')
                    ->orWhere('min_teams', '<=', $teamsCount);
            })
            ->where(function ($query) use ($teamsCount) {
                $query->whereNull('max_teams')
                    ->orWhere('max_teams', '>=', $teamsCount);
            })
            ->first();

        return $point ? $point->points : 0;
    }

    /**
     * Получить все активные типы турниров
     */
    public static function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
