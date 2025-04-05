<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $appends = ['page_image', 'default_page_image'];

    public function getPageImageAttribute(): ?string
    {
        if ($this->image) return config('app.url') . '/storage/' . $this->image;
        return null;
    }

    public function getDefaultPageImageAttribute(): ?string
    {
        if ($this->image_default) return config('app.url') . '/storage/' . $this->image_default;
        return null;
    }
}
