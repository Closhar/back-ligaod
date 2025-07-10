<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingActualityStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'is_actual',
    ];
}
