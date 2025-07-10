<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionYearTotalRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'rating_region_id',
        'rating_year_id',
        'rating',
        'yearly_rating',
    ];

    public function region()
    {
        return $this->belongsTo(RatingRegion::class, 'rating_region_id');
    }

    public function year()
    {
        return $this->belongsTo(RatingYear::class, 'rating_year_id');
    }
}
