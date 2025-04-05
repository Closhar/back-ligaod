<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPage extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $appends = ['page_image'];

    public function getPageImageAttribute(): ?string
    {
        if ($this->image) return config('app.url') . '/storage/' . $this->image;
        return null;
    }
}
