<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonAmpluaMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'amplua_id',
        'started_at',
        'ended_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Отношение к персоне
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Отношение к амплуа
     */
    public function amplua(): BelongsTo
    {
        return $this->belongsTo(Amplua::class);
    }
}
