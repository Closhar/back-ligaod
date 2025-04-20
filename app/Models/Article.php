<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];

    protected $hidden = ['pivot', 'created_at', 'updated_at'];
    protected $appends = ['date_formatted', 'article_image_path', 'event_name'];

    protected static function booted(): void
    {
        static::addGlobalScope('order', function ($builder) {
            $builder->orderBy('title', 'asc'); // или 'desc' для сортировки по убыванию
        });
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function sports(): MorphToMany
    {
        return $this->morphedByMany(Sport::class, 'articleable');
    }

    public function clubs(): MorphToMany
    {
        return $this->morphedByMany(Club::class, 'articleable');
    }

    public function arenas(): MorphToMany
    {
        return $this->morphedByMany(Arena::class, 'articleable');
    }

    public function competitions(): MorphToMany
    {
        return $this->morphedByMany(Competition::class, 'articleable');
    }

    public function events(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'articleable');
    }

    public function galleries(): MorphToMany
    {
        return $this->morphedByMany(Gallery::class, 'articleable');
    }

    public function videos(): MorphToMany
    {
        return $this->morphedByMany(Video::class, 'articleable');
    }

    public function getDateFormattedAttribute()
    {
        return \Carbon\Carbon::parse($this->data)->format('d.m.Y. H:i');

    }

    public function getArticleImagePathAttribute()
    {
        if ($this->image) return config('app.url') . '/storage/' . $this->image;
        return null;
    }

    public function getEventNameAttribute()
    {
        return $this->title;
    }
}
