<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'icon',
        'description',
        'menu',
        'menu_section_id',
        'sort_order'
    ];

    protected $casts = [
        'menu' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * Отношение к разделу меню
     */
    public function menuSection()
    {
        return $this->belongsTo(MenuSection::class);
    }

    /**
     * Scope для страниц в меню
     */
    public function scopeInMenu($query)
    {
        return $query->where('menu', true);
    }

    /**
     * Scope для сортировки по порядку
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Scope для активных разделов
     */
    public function scopeWithActiveSections($query)
    {
        return $query->whereHas('menuSection', function ($q) {
            $q->where('status', true);
        })->orWhereNull('menu_section_id');
    }

    public function getPageImageAttribute(): ?string
    {
        if ($this->image) return config('app.url') . '/storage/' . $this->image;
        return null;
    }
}