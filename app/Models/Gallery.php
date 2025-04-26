<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Gallery extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];

    protected $hidden = ['created_at', 'updated_at'];
    protected $appends = ['gallery_image_path'];
    public function main_image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'image_id', 'id');
        //->whereColumn('images.gallery_id', 'galleries.id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'gallery_id', 'id')
            ->orderBy('position');;
    }

    public function articles(): MorphToMany
    {
        return $this->morphToMany(Article::class, 'articleable');
    }

    public function getGalleryImagePathAttribute()
    {
        return config('app.url') . '/storage/' . $this->image;

    }

}
