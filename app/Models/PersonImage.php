<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PersonImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'image_path',
        'title',
        'position',
        'is_main',
        'alt_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'position' => 'integer',
        'is_main' => 'boolean',
    ];

    protected $appends = ['image_url'];

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
        return Storage::disk('public')->url($this->image_path);
    }

    /**
     * Scope для сортировки по позиции
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    /**
     * Scope для получения основного изображения
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }
}
