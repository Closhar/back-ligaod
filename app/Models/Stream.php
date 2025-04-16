<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    protected $guarded = [];

    public function events()
    {
        return $this->belongsTo(Event::class);
    }
}
