<?php

namespace App\Models;

use App\Traits\KTranslateTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Age extends Model
{
    use KTranslateTrait, HasFactory;

    protected $guarded = [];

    protected $hidden = ['created_at', 'updated_at'];

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
