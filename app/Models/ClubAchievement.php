<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'year',
        'tournament_type',
        'tournament_type_id',
        'division',
        'position',
        'teams_count',
        'promoted',
        'coefficient',
        'points_earned',
        'calculation_details'
    ];

    protected $casts = [
        'promoted' => 'boolean',
        'points_earned' => 'decimal:2',
        'coefficient' => 'decimal:2',
        'calculation_details' => 'array'
    ];

    /**
     * Клуб
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Тип турнира
     */
    public function tournamentType(): BelongsTo
    {
        return $this->belongsTo(TournamentType::class);
    }

    /**
     * Рассчитать очки по формуле SRRR
     */
    public function calculatePoints(): float
    {
        $points = 0;
        $details = [];

        // Получаем тип турнира
        $tournamentType = $this->tournamentType;

        if (!$tournamentType) {
            // Fallback к старой системе для обратной совместимости
            $points = $this->calculatePointsLegacy();
        } else {
            // Новая система с таблицей
            $points = $this->calculatePointsNew();
        }

        // Применяем коэффициент
        $coefficient = $this->coefficient ?? 1.0;
        $finalPoints = $points * $coefficient;

        $this->update([
            'points_earned' => $finalPoints,
            'calculation_details' => $details
        ]);

        return $finalPoints;
    }

    /**
     * Новая система расчета очков через таблицу
     */
    private function calculatePointsNew(): float
    {
        $tournamentType = $this->tournamentType;
        $position = $this->position;
        $teamsCount = $this->teams_count;
        $promoted = $this->promoted;

        // Получаем базовые очки за место
        $basePoints = $tournamentType->getPointsForPosition($position, $teamsCount);

        // Для первой лиги добавляем бонус за повышение
        if ($tournamentType->code === 'first_league' && $promoted) {
            $promotedBonus = $tournamentType->getPointsForPosition($position, $teamsCount);
            // Ищем запись с бонусом за повышение
            $promotedPoint = $tournamentType->points()
                ->where('position', $position)
                ->where('is_active', true)
                ->where('description', 'like', '%с бонусом за повышение%')
                ->first();

            if ($promotedPoint) {
                $basePoints = $promotedPoint->points;
            } else {
                // Fallback: добавляем 30 очков за повышение
                $basePoints += 30;
            }
        }

        // Для малых лиг (N < 8) учитываются только первые N/2 мест
        if ($teamsCount < 8) {
            $maxPositions = floor($teamsCount / 2);
            if ($position > $maxPositions) {
                return $promoted && $tournamentType->code === 'first_league' ? 30 : 0;
            }
        }

        return $basePoints;
    }

    /**
     * Старая система расчета очков (для обратной совместимости)
     */
    private function calculatePointsLegacy(): float
    {
        switch ($this->tournament_type) {
            case 'championship':
                return $this->calculateChampionshipPoints();
            case 'first_league':
                return $this->calculateFirstLeaguePoints();
            case 'cup':
                return $this->calculateCupPoints();
            case 'supercup':
                return $this->calculateSupercupPoints();
            default:
                return 0;
        }
    }

    /**
     * Расчет очков за чемпионат
     */
    private function calculateChampionshipPoints(): float
    {
        $n = $this->teams_count;
        $position = $this->position;

        // Для малых лиг (N < 8) учитываются только первые N/2 мест
        if ($n < 8) {
            $maxPositions = floor($n / 2);
            if ($position > $maxPositions) {
                return 0;
            }
        }

        $multiplier = $n / 10;

        switch ($position) {
            case 1: return 100 * $multiplier;
            case 2: return 80 * $multiplier;
            case 3: return 60 * $multiplier;
            case 4: return 20 * $multiplier;
            default: return 0;
        }
    }

    /**
     * Расчет очков за первую лигу
     */
    private function calculateFirstLeaguePoints(): float
    {
        $n = $this->teams_count;
        $position = $this->position;
        $promoted = $this->promoted;

        // Для малых лиг (N < 8) учитываются только первые N/2 мест
        if ($n < 8) {
            $maxPositions = floor($n / 2);
            if ($position > $maxPositions) {
                return $promoted ? 30 : 0;
            }
        }

        $multiplier = $n / 10;
        $basePoints = 0;

        switch ($position) {
            case 1: $basePoints = 50 * $multiplier; break;
            case 2: $basePoints = 30 * $multiplier; break;
            case 3: $basePoints = 20 * $multiplier; break;
            case 4: $basePoints = 5 * $multiplier; break;
            default: $basePoints = 0;
        }

        return $basePoints + ($promoted ? 30 : 0);
    }

    /**
     * Расчет очков за кубок
     */
    private function calculateCupPoints(): float
    {
        switch ($this->position) {
            case 1: return 50; // Победа
            case 2: return 30; // Финал
            case 3: return 20; // Полуфинал
            case 4: return 20; // Полуфинал
            default: return 0;
        }
    }

    /**
     * Расчет очков за суперкубок
     */
    private function calculateSupercupPoints(): float
    {
        switch ($this->position) {
            case 1: return 30; // Победа
            case 2: return 10; // Участие
            default: return 0;
        }
    }
}
