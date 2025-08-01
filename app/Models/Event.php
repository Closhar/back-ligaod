<?php

namespace App\Models;

use App\Models\EventAction;
use App\Models\EventTeamAction;
use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Event extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at', 'pivot'];
    protected $appends = ['sport_icon', 'gender_icon', 'event_name', 'event_name_top', 'event_image_path', 'date_formatted', 'time_formatted', 'date_to_formatted', 'time_to_formatted'];

    // This enables automatic counting when loading Events
    protected $withCount = ['streams'];
    protected $dates = ['date_from'];

    protected $casts = [
        'show_numbers_club1' => 'boolean',
        'show_numbers_club2' => 'boolean',
    ];

    protected $fillable = [
        'title',
        'date_from',
        'date_to',
        'result',
        'result_dop',
        'image',
        'competition_id',
        'arena_id',
        'club1_id',
        'club2_id',
        'region_id',
        'is_active',
        'event_name',
        'event_name_top',
        'series_id',
        'series_count',
        'about',
        'tickets',
        'free_tickets',
        'report',
        'display_lineups_mode',
        'display_actions_mode',
        'show_numbers_club1',
        'show_numbers_club2',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function club1(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club1_id');
    }

    public function club2(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club2_id');
    }

    // Алиасы для совместимости с API матчей
    public function home_team(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club1_id');
    }

    public function away_team(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club2_id');
    }

    public function sport()
    {
        return $this->hasOneThrough(Sport::class, Competition::class, 'id', 'id', 'competition_id', 'sport_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EventAction::class);
    }

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function articles(): MorphToMany
    {
        return $this->morphToMany(Article::class, 'articleable');
    }

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    public function lineups()
    {
        return $this->hasMany(EventLineup::class);
    }

    public function actions()
    {
        return $this->hasMany(EventAction::class);
    }

    public function eventTeamActions()
    {
        return $this->hasMany(EventTeamAction::class);
    }

    public function getDateFormattedAttribute()
    {
        return \Carbon\Carbon::parse($this->date_from)->format('d.m.Y.');
    }

    public function getDateFromAttribute($value)
    {
        return $value; // Возвращаем как есть
    }

    public function setDateFromAttribute($value)
    {
        $this->attributes['date_from'] = $value; // Сохраняем без преобразований
    }

    public function getDateToFormattedAttribute()
    {
        return \Carbon\Carbon::parse($this->date_to)->format('d.m.Y.');
    }

    public function getTimeFormattedAttribute()
    {
        $t = \Carbon\Carbon::parse($this->date_from)->format('H:i');
        if ($t == '00:00') $t = '--:--';
        return $t;
    }

    public function getTimeToFormattedAttribute()
    {
        $t = \Carbon\Carbon::parse($this->date_to)->format('H:i');
        if ($t == '00:00') $t = '--:--';
        return $t;
    }

    public function getEventImagePathAttribute()
    {
        if ($this->image) return config('app.url') . '/storage/' . $this->image;
        return null;
    }

    public function getEventNameAttribute()
    {
        // Добавляем параметр event_name_last
        if (!$this->club1) return $this->competition?->title_short . '. ' . $this->title;
        else
            return $this->date_formatted . ' ' . ($this->club1->title ?? 'Клуб 1') . ' (' . ($this->club1->city->title ?? 'Город не указан') . ') - ' . ($this->club2->title ?? 'Клуб 2') . ' (' . ($this->club2->city->title ?? 'Город не указан') . ') ' . $this->result;
    }

    public function getEventNameTopAttribute()
    {
        // Добавляем параметр event_name_last
        if (!$this->club1) return $this->title;
        else
            return ($this->club1->title ?? 'Клуб 1') . ' (' . ($this->club1->city->title ?? 'Город не указан') . ') - ' . ($this->club2->title ?? 'Клуб 2') . ' (' . ($this->club2->city->title ?? 'Город не указан') . ') ';
    }

    public function getSportIconAttribute()
    {
        return $this->competition?->sport?->icon ?? null;
    }

    public function getGenderIconAttribute()
    {
        return $this->competition?->gender?->icon ?? null;
    }
}
