<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactEmail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
