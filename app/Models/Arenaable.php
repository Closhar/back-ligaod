<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Arenaable extends Model
{
    protected $table = 'arenaables';
    protected $guarded = [];

    public function arenaable(): MorphTo
    {
        return $this->morphTo('arenaable', 'arenaableable_type', 'arenaable_id');
    }

}
