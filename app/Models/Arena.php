<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Arena extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];

    protected $hidden = ['created_at', 'updated_at', 'pivot'];
    protected $appends = ['arena_image_path', 'event_name'];

    protected static function booted(): void
    {
        static::addGlobalScope('order', function ($builder) {
            $builder->orderBy('title', 'asc'); // или 'desc' для сортировки по убыванию
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function sports(): MorphToMany
    {
        return $this->morphedByMany(Sport::class, 'arenaable');
    }

    public function clubs(): MorphToMany
    {
        return $this->morphedByMany(Club::class, 'arenaable');
    }

    public function competitions(): MorphToMany
    {
        return $this->morphedByMany(Competition::class, 'arenaable');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function articles(): MorphToMany
    {
        return $this->morphToMany(Article::class, 'articleable');
    }

    public function getArenaImagePathAttribute()
    {
        return config('app.url') . '/storage/' . $this->image;

    }

    public function getEventNameAttribute()
    {
        return $this->title;
    }

}
