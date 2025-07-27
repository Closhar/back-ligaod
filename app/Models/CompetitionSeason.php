<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionSeason extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at'];
    protected $appends = ['date_from_formatted', 'date_to_formatted', 'duration_days'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Отношение к соревнованию
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * Отношение к сезону
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Scope для активных сезонов
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для сезонов по соревнованию
     */
    public function scopeByCompetition(Builder $query, int $competitionId): Builder
    {
        return $query->where('competition_id', $competitionId);
    }

    /**
     * Scope для сезонов в определенном диапазоне дат
     */
    public function scopeInDateRange(Builder $query, string $dateFrom, string $dateTo): Builder
    {
        return $query->where(function ($q) use ($dateFrom, $dateTo) {
            $q->whereBetween('date_from', [$dateFrom, $dateTo])
              ->orWhereBetween('date_to', [$dateFrom, $dateTo])
              ->orWhere(function ($subQ) use ($dateFrom, $dateTo) {
                  $subQ->where('date_from', '<=', $dateFrom)
                       ->where('date_to', '>=', $dateTo);
              });
        });
    }

    /**
     * Форматированная дата начала
     */
    public function getDateFromFormattedAttribute(): ?string
    {
        return $this->date_from ? $this->date_from->format('d.m.Y') : null;
    }

    /**
     * Форматированная дата окончания
     */
    public function getDateToFormattedAttribute(): ?string
    {
        return $this->date_to ? $this->date_to->format('d.m.Y') : null;
    }

    /**
     * Количество дней в сезоне
     */
    public function getDurationDaysAttribute(): ?int
    {
        if ($this->date_from && $this->date_to) {
            return $this->date_from->diffInDays($this->date_to) + 1;
        }
        return null;
    }

    /**
     * Проверка, активен ли сезон на определенную дату
     */
    public function isActiveOnDate(string $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $checkDate = \Carbon\Carbon::parse($date);

        if ($this->date_from && $checkDate->lt($this->date_from)) {
            return false;
        }

        if ($this->date_to && $checkDate->gt($this->date_to)) {
            return false;
        }

        return true;
    }

    /**
     * Проверка, пересекается ли сезон с другим сезоном
     */
    public function overlapsWith(CompetitionSeason $otherSeason): bool
    {
        if ($this->competition_id !== $otherSeason->competition_id) {
            return false;
        }

        if (!$this->date_from || !$this->date_to || !$otherSeason->date_from || !$otherSeason->date_to) {
            return false;
        }

        return $this->date_from <= $otherSeason->date_to && $this->date_to >= $otherSeason->date_from;
    }
}
