<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonRoleMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'role_id',
        'started_at',
        'ended_at',
        'notes',
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
     * Отношение к роли
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Проверить, является ли членство активным
     */
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    /**
     * Получить продолжительность роли в днях
     */
    public function getRoleDurationAttribute(): int
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
     * Scope для членств в определенной роли
     */
    public function scopeInRole($query, $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Scope для спортсменов
     */
    public function scopeSportsman($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('type', 'sportsman');
        });
    }

    /**
     * Scope для не спортсменов
     */
    public function scopeNonSportsman($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('type', 'non_sportsman');
        });
    }
}
