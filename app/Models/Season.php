<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'name',
        'date_from',
        'date_to',
        'is_active'
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Связь с соревнованиями через промежуточную таблицу
     */
    public function competitions()
    {
        return $this->belongsToMany(Competition::class, 'competition_seasons')
                    ->withTimestamps();
    }

    /**
     * Связь с competition_seasons
     */
    public function competitionSeasons()
    {
        return $this->hasMany(CompetitionSeason::class);
    }

    /**
     * Получить активные сезоны
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Получить сезоны по дате
     */
    public function scopeByDate($query, $date)
    {
        return $query->where('date_from', '<=', $date)
                    ->where(function($q) use ($date) {
                        $q->where('date_to', '>=', $date)
                          ->orWhereNull('date_to');
                    });
    }
}
