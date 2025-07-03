<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Sport extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];

    protected $hidden = ['updated_at', 'created_at', 'pivot'];
    protected $appends = ['event_name', 'name'];

    protected static function booted(): void
    {
        static::addGlobalScope('order', function ($builder) {
            $builder->orderBy('title', 'asc'); // или 'desc' для сортировки по убыванию
        });
    }

    public function sport_properties(): BelongsToMany
    {
        return $this->belongsToMany(SportProperty::class, 'sport_sport_property', 'sport_id', 'sport_property_id');
    }

    public function arenas(): MorphToMany
    {
        return $this->morphToMany(Arena::class, 'arenaable');
    }

    public function articles(): MorphToMany
    {
        return $this->morphToMany(Article::class, 'articleable');
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

// Отношение к событиям через соревнования
    public function events(): HasManyThrough
    {
        return $this->hasManyThrough(
            Event::class,        // Целевая модель (Event)
            Competition::class,  // Промежуточная модель (Competition)
            'sport_id',         // Внешний ключ в промежуточной таблице (competitions.sport_id)
            'competition_id',    // Внешний ключ в целевой таблице (events.competition_id)
            'id',               // Локальный ключ в текущей таблице (sports.id)
            'id'                // Локальный ключ в промежуточной таблице (competitions.id)
        );
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }


    public function getEventNameAttribute()
    {
        return $this->title;
    }

    /**
     * Получить название вида спорта (алиас для совместимости с фронтендом)
     */
    public function getNameAttribute(): string
    {
        return $this->title ?? '';
    }
}
