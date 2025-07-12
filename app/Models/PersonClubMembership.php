<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonClubMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'club_id',
        'joined_at',
        'left_at',
        'position',
        'notes',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    /**
     * Отношение к персоне
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Отношение к клубу
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Отношение к амплуа
     */
    public function amplua(): BelongsTo
    {
        return $this->belongsTo(Amplua::class, 'amplua_id');
    }

    /**
     * Проверить, является ли членство активным
     */
    public function isActive(): bool
    {
        return is_null($this->left_at);
    }

    /**
     * Получить продолжительность членства в днях
     */
    public function getMembershipDurationAttribute(): int
    {
        $endDate = $this->left_at ?? now();
        return $this->joined_at->diffInDays($endDate);
    }

    /**
     * Scope для активных членств
     */
    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    /**
     * Scope для исторических членств
     */
    public function scopeHistorical($query)
    {
        return $query->whereNotNull('left_at');
    }

    /**
     * Scope для членств в определенном клубе
     */
    public function scopeInClub($query, $clubId)
    {
        return $query->where('club_id', $clubId);
    }
}
