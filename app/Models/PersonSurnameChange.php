<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSurnameChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'old_surname',
        'valid_until',
    ];

    protected $casts = [
        'valid_until' => 'date',
    ];

    /**
     * Отношение к персоне
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Scope для получения актуальных смен фамилий
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>=', now()->toDateString());
        });
    }

    /**
     * Scope для получения исторических смен фамилий
     */
    public function scopeHistorical($query)
    {
        return $query->where('valid_until', '<', now()->toDateString());
    }
}
