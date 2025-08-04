<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLineup extends Model
{
    protected $fillable = [
        'event_id',
        'club_id',
        'person_id',
        'player_name',
        'number',
        'parent_lineup_id',
        'minute_in',
        'minute_out',
        'sort_order',
        'is_captain',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
    ];

    protected $appends = [
        'full_name'
    ];

    public function getFullNameAttribute()
    {
        return $this->person ? $this->person->full_name : $this->player_name;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function parentLineup()
    {
        return $this->belongsTo(EventLineup::class, 'parent_lineup_id');
    }

    public function substitutions()
    {
        return $this->hasMany(EventLineup::class, 'parent_lineup_id');
    }
}
