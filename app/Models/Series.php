<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Series extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'title_short',
        'description',
        'match_info',
        'series_type_id'
    ];

    public function seriesType()
    {
        return $this->belongsTo(SeriesType::class, 'series_type_id', 'id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'series_id', 'id');
    }
}
