<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Competition extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at', 'pivot'];
    protected $appends = ['date_from_formatted', 'date_to_formatted', 'competition_image_path', 'competition_bg_image_path', 'event_name'];

    public function arenas(): MorphToMany
    {
        return $this->morphToMany(Arena::class, 'arenaable');
    }

    public function articles(): MorphToMany
    {
        return $this->morphToMany(Article::class, 'articleable');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function parseTable(): BelongsTo
    {
        return $this->belongsTo(ParseTable::class);
    }

    public function clubs1()
    {
        return $this->hasManyThrough(
            Club::class, // Целевая модель (Club)
            Event::class, // Промежуточная модель (Event)
            'competition_id', // Внешний ключ в промежуточной таблице (events)
            'id', // Внешний ключ в целевой таблице (clubs)
            'id', // Локальный ключ в текущей таблице (competitions)
            'club1_id' // Локальный ключ в промежуточной таблице (events)
        );
    }

    public function clubs2()
    {
        return $this->hasManyThrough(
            Club::class, // Целевая модель (Club)
            Event::class, // Промежуточная модель (Event)
            'competition_id', // Внешний ключ в промежуточной таблице (events)
            'id', // Внешний ключ в целевой таблице (clubs)
            'id', // Локальный ключ в текущей таблице (competitions)
            'club2_id' // Локальный ключ в промежуточной таблице (events)
        );
    }

    public function getDateFromFormattedAttribute()
    {
        if ($this->date_from) return \Carbon\Carbon::parse($this->date_from)->format('d.m.Y.');
        return null;
    }

    public function getDateToFormattedAttribute()
    {
        if ($this->date_to) return \Carbon\Carbon::parse($this->date_to)->format('d.m.Y.');
        return null;

    }

    public function getCompetitionImagePathAttribute()
    {
        if ($this->image) return config('app.url') . '/storage/' . $this->image;
        return null;
    }

    public function getCompetitionBgImagePathAttribute()
    {
        if ($this->bg_image) return config('app.url') . '/storage/' . $this->bg_image;
        return null;

    }

    public function getEventNameAttribute()
    {
        return $this->title;
    }

}
