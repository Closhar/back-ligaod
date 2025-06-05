<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'date',
        'title',
        'link',
        'in_player',
        'in_profile',
        'event_id'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id')->with(['club1.city', 'club2.city', 'competition.sport', 'competition.gender']);
    }
}
