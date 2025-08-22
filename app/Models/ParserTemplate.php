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
        try {
            if ($type === 'css') {
                // Используем DOMDocument для более точного извлечения
                $dom = new \DOMDocument();
                @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($dom);

                // Конвертируем CSS селектор в XPath (базовая поддержка)
                $xpathSelector = $this->cssToXpath($selector);
                $nodes = $xpath->query($xpathSelector);

                if ($nodes && $nodes->length > 0) {
                    $value = '';
                    foreach ($nodes as $node) {
                        $value .= $node->textContent . ' ';
                    }
                    return trim($value);
                }
            } elseif ($type === 'xpath') {
                $dom = new \DOMDocument();
                @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($dom);

                $nodes = $xpath->query($selector);

                if ($nodes && $nodes->length > 0) {
                    $value = '';
                    foreach ($nodes as $node) {
                        $value .= $node->textContent . ' ';
                    }
                    return trim($value);
                }
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \Log::warning('Error extracting value from HTML: ' . $e->getMessage(), [
                'selector' => $selector,
                'type' => $type
            ]);
        }

        return '';
    }

    /**
     * Простая конвертация CSS селектора в XPath
     */
    private function cssToXpath(string $cssSelector): string
    {
        // Базовые CSS селекторы
        $cssSelector = trim($cssSelector);

        // Класс
        if (substr($cssSelector, 0, 1) === '.') {
            $className = substr($cssSelector, 1);
            return "//*[contains(@class, '$className')]";
        }
        
        // ID
        if (substr($cssSelector, 0, 1) === '#') {
            $idName = substr($cssSelector, 1);
            return "//*[@id='$idName']";
        }

        // Тег
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $cssSelector)) {
            return "//$cssSelector";
        }

        // Комбинированный селектор (например, div.class)
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*)\.([a-zA-Z][a-zA-Z0-9]*)$/', $cssSelector, $matches)) {
            $tag = $matches[1];
            $class = $matches[2];
            return "//$tag[contains(@class, '$class')]";
        }

        // По умолчанию возвращаем как есть (предполагая, что это уже XPath)
        return $cssSelector;
    }
}
