<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
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
        'subdomain',
    ];

    /**
     * Get the articles associated with the region.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Get the events associated with the region.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Get the arenas associated with the region.
     */
    public function arenas(): HasMany
    {
        return $this->hasMany(Arena::class);
    }

    /**
     * Get the clubs associated with the region.
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
