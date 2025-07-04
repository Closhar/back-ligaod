<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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
        'calculation_details',
        'is_farm'
    ];

    protected $casts = [
        'promoted' => 'boolean',
        'points_earned' => 'decimal:2',
        'coefficient' => 'decimal:2',
        'calculation_details' => 'array',
        'is_farm' => 'boolean'
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
            $coefficient = 1.0;
        } else {
            // Новая система с таблицей
            $points = $this->calculatePointsNew();
            // Получаем коэффициент из метода calculatePointsNew
            $coefficient = $this->getCalculatedCoefficient();
        }

        $this->update([
            'points_earned' => $points,
            'coefficient' => $coefficient,
            'calculation_details' => $details
        ]);

        return $points;
    }

    /**
     * Получить рассчитанный коэффициент
     */
    private function getCalculatedCoefficient(): float
    {
        $tournamentType = $this->tournamentType;
        $isFarm = $this->is_farm;

        if (!$tournamentType) {
            return 1.0;
        }

        // Если фарм-клуб — применяем коэффициент из настроек турнира
        if ($isFarm == true) {
            $coefficient = $tournamentType->coefficient ?? 0.5;
            return $coefficient;
        } else {
            // Для обычных клубов всегда применяем коэффициент 1.0
            return 1.0;
        }
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
        $isFarm = $this->is_farm;

        // Ограничение по местам для малых лиг (N < 8) — для всех типов, кроме кубка
        if ($teamsCount < 8 && $tournamentType->code !== 'cup') {
            $maxPositions = floor($teamsCount / 2);
            if ($position > $maxPositions) {
                // Для первой лиги с повышением — бонус из настроек турнира, иначе 0
                if ($promoted && $tournamentType->code === 'first_league') {
                    $promotionBonus = $tournamentType->promotion_bonus ?? 30;
                    return $promotionBonus;
                }
                return 0;
            }
        }

        // Получаем базовые очки за место
        $basePoints = $tournamentType->getPointsForPosition($position, $teamsCount);

        // Если нет очков за место, но есть очки за участие, используем их
        if ($basePoints === 0 && $tournamentType->participation_points > 0) {
            $basePoints = $tournamentType->participation_points;
        }

        // Применяем множитель по количеству команд, если не установлен флаг ignore_teams_multiplier
        if (!$tournamentType->ignore_teams_multiplier) {
            $multiplier = $teamsCount / 10;
            $basePoints = $basePoints * $multiplier;
        }

        // Для первой лиги добавляем бонус за повышение
        if ($tournamentType->code === 'first_league' && $promoted) {
            // Ищем запись с бонусом за повышение
            $promotedPoint = $tournamentType->points()
                ->where('position', $position)
                ->where('is_active', true)
                ->where('description', 'like', '%с бонусом за повышение%')
                ->first();

            if ($promotedPoint) {
                // Если есть специальная запись с бонусом, используем её
                $basePoints = $promotedPoint->points;
                if (!$tournamentType->ignore_teams_multiplier) {
                    $multiplier = $teamsCount / 10;
                    $basePoints = $basePoints * $multiplier;
                }
            } else {
                // Используем бонус за повышение из настроек турнира
                $promotionBonus = $tournamentType->promotion_bonus ?? 30;
                $basePoints += $promotionBonus;
            }
        }

        // Применяем коэффициент
        $coefficient = $this->getCalculatedCoefficient();
        $basePoints = $basePoints * $coefficient;

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
                // Используем бонус за повышение из настроек турнира
                $promotionBonus = $this->tournamentType ? ($this->tournamentType->promotion_bonus ?? 30) : 30;
                return $promoted ? $promotionBonus : 0;
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

        // Используем бонус за повышение из настроек турнира
        $promotionBonus = $this->tournamentType ? ($this->tournamentType->promotion_bonus ?? 30) : 30;
        return $basePoints + ($promoted ? $promotionBonus : 0);
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
