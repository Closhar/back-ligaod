<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTypePoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_type_id',
        'position',
        'points',
        'min_teams',
        'max_teams',
        'is_active',
        'description'
    ];

    protected $casts = [
        'position' => 'integer',
        'points' => 'integer',
        'min_teams' => 'integer',
        'max_teams' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Тип турнира
     */
    public function tournamentType(): BelongsTo
    {
        return $this->belongsTo(TournamentType::class);
    }
}
