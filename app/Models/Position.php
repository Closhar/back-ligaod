<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Отношение к членствам в должностях
     */
    public function positionMemberships(): HasMany
    {
        return $this->hasMany(PersonPositionMembership::class);
    }

    /**
     * Отношение к персонам через членство
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_position_memberships')
            ->withPivot(['started_at', 'ended_at', 'notes'])
            ->withTimestamps();
    }

    /**
     * Получить активные членства в этой должности
     */
    public function activeMemberships(): HasMany
    {
        return $this->hasMany(PersonPositionMembership::class)->whereNull('ended_at');
    }

    /**
     * Получить активные членства в этой должности (алиас для совместимости)
     */
    public function activePositionMemberships(): HasMany
    {
        return $this->activeMemberships();
    }

    /**
     * Scope для активных должностей
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
