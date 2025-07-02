<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSportMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'sport_id',
        'started_at',
        'ended_at',
        'level',
        'achievements',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    /**
     * Отношение к персоне
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Отношение к виду спорта
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Проверить, является ли членство активным
     */
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    /**
     * Получить продолжительность занятий спортом в днях
     */
    public function getSportDurationAttribute(): int
    {
        $endDate = $this->ended_at ?? now();
        return $this->started_at->diffInDays($endDate);
    }

    /**
     * Scope для активных членств
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope для исторических членств
     */
    public function scopeHistorical($query)
    {
        return $query->whereNotNull('ended_at');
    }

    /**
     * Scope для членств в определенном виде спорта
     */
    public function scopeInSport($query, $sportId)
    {
        return $query->where('sport_id', $sportId);
    }

    /**
     * Scope для определенного уровня
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }
}
