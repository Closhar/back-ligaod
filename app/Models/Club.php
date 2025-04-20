<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Club extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];

    protected $hidden = ['created_at', 'updated_at', 'pivot'];
    protected $appends = ['club_image_path', 'bg_club_image_path', 'event_name'];

    protected static function booted(): void
    {
        static::addGlobalScope('order', function ($builder) {
            $builder->orderBy('clubs.title', 'asc'); // или 'desc' для сортировки по убыванию
        });
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function age(): BelongsTo
    {
        return $this->belongsTo(Age::class);
    }

    public function arenas(): MorphToMany
    {
        return $this->morphToMany(Arena::class, 'arenaable');
    }

    public function articles(): MorphToMany
    {
        return $this->morphToMany(Article::class, 'articleable');
    }

    public function club1_events(): HasMany
    {
        return $this->hasMany(Event::class, 'club1_id');
    }

    public function club2_events(): HasMany
    {
        return $this->hasMany(Event::class, 'club2_id');
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function getClubImagePathAttribute()
    {
        return config('app.url') . '/storage/' . $this->image;

    }

    public function getBgClubImagePathAttribute()
    {
        if ($this->image_bg) return config('app.url') . '/storage/' . $this->image_bg;
        return null;
    }

    public function getEventNameAttribute()
    {
        $cityTitle = $this->city ? $this->city->title : 'Город не указан';
        return $this->title . ' (' . $cityTitle . ')';
    }

}
