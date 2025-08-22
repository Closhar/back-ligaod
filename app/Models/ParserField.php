<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParserField extends Model
{
    protected $fillable = [
        'parser_template_id',
        'name',
        'selector',
        'selector_type',
        'data_type',
        'extraction_rules',
        'target_table',
        'target_field',
        'update_strategy',
        'is_required',
        'order',
    ];

    protected $casts = [
        'extraction_rules' => 'array',
        'is_required' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ParserTemplate::class, 'parser_template_id');
    }

    public function extractValue(string $html): mixed
    {
        $value = $this->extractRawValue($html);

        if (empty($value)) {
            return null;
        }

        return $this->processValue($value);
    }

    private function extractRawValue(string $html): string
    {
        if ($this->selector_type === 'xpath') {
            return $this->extractByXPath($html);
        }

        return $this->extractByCss($html);
    }

    private function extractByCss(string $html): string
    {
        // Простая реализация CSS селектора
        // В реальном проекте лучше использовать DOMDocument
        if (preg_match('/' . preg_quote($this->selector, '/') . '[^>]*>(.*?)<\/[^>]*>/s', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return '';
    }

    private function extractByXPath(string $html): string
    {
        // Простая реализация XPath
        // В реальном проекте лучше использовать DOMXPath
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $nodes = $xpath->query($this->selector);

        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return '';
    }

    private function processValue(string $value): mixed
    {
        if (empty($this->extraction_rules)) {
            return $value;
        }

        foreach ($this->extraction_rules as $rule) {
            if (!isset($rule['type'])) {
                continue;
            }

            switch ($rule['type']) {
                case 'regex':
                    if (isset($rule['pattern']) && preg_match($rule['pattern'], $value, $matches)) {
                        $value = $matches[1] ?? $value;
                    }
                    break;

                case 'replace':
                    if (isset($rule['search'], $rule['replace'])) {
                        $value = str_replace($rule['search'], $rule['replace'], $value);
                    }
                    break;

                case 'trim':
                    $value = trim($value);
                    break;

                case 'lowercase':
                    $value = strtolower($value);
                    break;

                case 'uppercase':
                    $value = strtoupper($value);
                    break;
            }
        }

        return $value;
    }
}
