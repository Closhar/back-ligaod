<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'description',
        'sort_order',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * Отношение к страницам админки
     */
    public function adminPages()
    {
        return $this->hasMany(AdminPage::class)->orderBy('sort_order', 'asc');
    }

    /**
     * Получить активные страницы админки для этого раздела
     */
    public function activeAdminPages()
    {
        return $this->adminPages()->where('menu', true)->orderBy('sort_order', 'asc');
    }

    /**
     * Scope для активных разделов
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope для сортировки по порядку
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}