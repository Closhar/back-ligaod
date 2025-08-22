<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParserTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'url_pattern',
        'conditions',
        'headers',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(ParserField::class)->orderBy('order');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ParserLog::class);
    }

    public function lastLog(): HasOne
    {
        return $this->hasOne(ParserLog::class)->latest();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function matchesUrl(string $url): bool
    {
        return preg_match($this->url_pattern, $url);
    }

    public function shouldParse(string $html): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            if (!isset($condition['type'], $condition['selector'])) {
                continue;
            }

            $value = $this->extractValue($html, $condition['selector'], $condition['type'] ?? 'css');

            if (isset($condition['required']) && $condition['required'] && empty($value)) {
                return false;
            }

            if (isset($condition['contains']) && !str_contains($value, $condition['contains'])) {
                return false;
            }

            if (isset($condition['equals']) && $value !== $condition['equals']) {
                return false;
            }
        }

        return true;
    }

    private function extractValue(string $html, string $selector, string $type): string
    {
        // Простая реализация извлечения значения
        // В реальном проекте лучше использовать DOMDocument или Symfony DomCrawler
        if ($type === 'css') {
            // Простое извлечение по CSS селектору
            if (preg_match('/' . preg_quote($selector, '/') . '[^>]*>(.*?)<\/[^>]*>/s', $html, $matches)) {
                return trim(strip_tags($matches[1]));
            }
        }

        return '';
    }
}
