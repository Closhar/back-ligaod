<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    use KTranslateTrait, HasFactory;

    const POSITION_GAP = 60000;
    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at', 'position', 'gallery_id'];

    protected $appends = ['thumbnail', 'gallery_image_path']; // Добавляем thumbnail в JSON-ответ


    public static function booted()
    {
        static::creating(function ($model) {
            $model->position = self::query()->where('gallery_id', $model->gallery_id)->orderByDesc('position')->first()?->position + self::POSITION_GAP;
        });
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function getThumbnailAttribute()
    {
        if ($this->image) {
            $thumbnail = preg_replace('~^(.*/)([^/]+)$~', '$1thmb_$2', $this->image);
            return config('app.url') . '/storage/' . $thumbnail; // Добавляем базовый URL
        }
        return null; // Если image отсутствует, вернется null
    }

    public function getGalleryImagePathAttribute()
    {
        if ($this->image) {
            return config('app.url') . '/storage/' . $this->image; // Добавляем базовый URL
        }
        return null; // Если image отсутствует, вернется null
    }
}
