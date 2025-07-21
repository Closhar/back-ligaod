<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventImage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $fillable = [
        'event_id',
        'path',
        'type',
        'preview_path',
        'position'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
