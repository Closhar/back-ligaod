<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Отношение к членствам в ролях
     */
    public function roleMemberships(): HasMany
    {
        return $this->hasMany(PersonRoleMembership::class);
    }

    /**
     * Отношение к персонам через членство
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_role_memberships')
            ->withPivot(['started_at', 'ended_at', 'notes'])
            ->withTimestamps();
    }

    /**
     * Получить активные членства в этой роли
     */
    public function activeMemberships(): HasMany
    {
        return $this->hasMany(PersonRoleMembership::class)->whereNull('ended_at');
    }

    /**
     * Scope для спортсменов
     */
    public function scopeSportsman($query)
    {
        return $query->where('type', 'sportsman');
    }

    /**
     * Scope для не спортсменов
     */
    public function scopeNonSportsman($query)
    {
        return $query->where('type', 'non_sportsman');
    }

    /**
     * Scope для активных ролей
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Проверить, является ли роль для спортсменов
     */
    public function isSportsmanRole(): bool
    {
        return $this->type === 'sportsman';
    }

    /**
     * Проверить, является ли роль для не спортсменов
     */
    public function isNonSportsmanRole(): bool
    {
        return $this->type === 'non_sportsman';
    }
}
