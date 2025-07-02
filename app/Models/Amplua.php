<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amplua extends Model
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
     * Отношение к членствам в амплуа
     */
    public function ampluaMemberships(): HasMany
    {
        return $this->hasMany(PersonAmpluaMembership::class);
    }

    /**
     * Отношение к персонам через членство
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_amplua_memberships')
            ->withPivot(['started_at', 'ended_at', 'notes'])
            ->withTimestamps();
    }

    /**
     * Получить активные членства в этом амплуа
     */
    public function activeMemberships(): HasMany
    {
        return $this->hasMany(PersonAmpluaMembership::class)->whereNull('ended_at');
    }

    /**
     * Scope для активных амплуа
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
