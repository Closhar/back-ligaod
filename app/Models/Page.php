<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Page extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $appends = ['page_image', 'default_page_image'];

    protected $casts = [
        'in_menu' => 'boolean',
        'in_mobile_menu' => 'boolean',
        'menu_sort' => 'integer',
        'mobile_menu_sort' => 'integer',
    ];

    public function getPageImageAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function getDefaultPageImageAttribute(): ?string
    {
        return $this->image_default ? Storage::disk('public')->url($this->image_default) : null;
    }
}
