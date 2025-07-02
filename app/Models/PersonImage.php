<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'image_path',
        'alt_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Отношение к персоне
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Получить полный URL изображения
     */
    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    /**
     * Scope для сортировки по порядку
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope для получения основного изображения
     */
    public function scopeMain($query)
    {
        return $query->orderBy('sort_order')->limit(1);
    }
}
