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
        try {
            // Используем DOMDocument для более надежного извлечения
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($dom);

            // Конвертируем CSS селектор в XPath
            $xpathSelector = $this->cssToXpath($this->selector);
            $nodes = $xpath->query($xpathSelector);

            if ($nodes && $nodes->length > 0) {
                $value = '';
                foreach ($nodes as $node) {
                    $value .= $node->textContent . ' ';
                }
                return trim($value);
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \Log::warning('Error extracting CSS value: ' . $e->getMessage(), [
                'selector' => $this->selector,
                'field_id' => $this->id
            ]);
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

    /**
     * Конвертирует CSS селектор в XPath
     */
    private function cssToXpath(string $cssSelector): string
    {
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
            return '//' . $tag . '[contains(@class, "' . $class . '")]';
        }

        // По умолчанию возвращаем как есть (предполагая, что это уже XPath)
        return $cssSelector;
    }
}
