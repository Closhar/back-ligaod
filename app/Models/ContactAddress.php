<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactAddress extends Model
{
    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_main' => 'boolean',
        'sort_order' => 'integer',
    ];
}
