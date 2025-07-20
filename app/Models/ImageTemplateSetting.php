<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageTemplateSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'type',
        'width',
        'height',
        'icon',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];
}
