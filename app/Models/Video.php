<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Video extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at'];


    public function articles(): MorphToMany
    {
        return $this->morphToMany(Article::class, 'articleable');
    }
}
