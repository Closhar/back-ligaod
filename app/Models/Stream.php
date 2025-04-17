<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(Event::class)->with(['club1.city', 'club2.city', 'competition.sport', 'competition.gender']);
    }
}
