<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TelegramChannel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'chat_id',
        'type',
        'username',
        'is_active',
        'description',
        'settings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array'
    ];
}