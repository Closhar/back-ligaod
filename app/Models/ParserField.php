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
        'search_context',
        'search_phrase',
        'value_separator',
        'team_identification',
        'result_format',
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
        // Если есть специальные правила извлечения, используем их
        if (!empty($this->extraction_rules)) {
            $value = $this->extractBySearchPhrase($html);
            if ($value !== null) {
                return $this->processValue($value);
            }
        }

        // Если есть настройки умного парсинга, используем их
        if (!empty($this->search_phrase)) {
            $value = $this->extractBySmartParsing($html);
            if ($value !== null) {
                // Для событий команд возвращаем массив с двумя значениями
                if ($this->target_table === 'event_teams' && $this->result_format === 'team_stats') {
                    return $this->formatTeamStatsValue($value);
                }
                return $this->processValue($value);
            }
        }

        $value = $this->extractRawValue($html);

        if (empty($value)) {
            return null;
        }

        // Для событий команд возвращаем массив с двумя значениями
        if ($this->target_table === 'event_teams') {
            return $this->formatRawValueForTeamEvents($value);
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

    /**
     * Извлекает значение по поисковой фразе и правилам
     */
    private function extractBySearchPhrase(string $html): ?string
    {
        foreach ($this->extraction_rules as $rule) {
            if ($rule['type'] !== 'search_phrase') {
                continue;
            }

            $searchPhrase = $rule['phrase'] ?? '';
            $contextPhrase = $rule['context'] ?? '';
            $separator = $rule['separator'] ?? '-';
            $maxResults = $rule['max_results'] ?? 10;

            if (empty($searchPhrase)) {
                continue;
            }

            // Ищем контекст (например, "Статистика матча:")
            $contextStart = 0;
            if (!empty($contextPhrase)) {
                $contextPos = stripos($html, $contextPhrase);
                if ($contextPos === false) {
                    continue; // Контекст не найден
                }
                $contextStart = $contextPos;
            }

            // Ищем поисковую фразу в контексте
            $searchPos = stripos(substr($html, $contextStart), $searchPhrase);
            if ($searchPos === false) {
                continue; // Поисковая фраза не найдена
            }

            $searchPos += $contextStart;
            $valueStart = $searchPos + strlen($searchPhrase);

            // Извлекаем текст после поисковой фразы
            $remainingText = substr($html, $valueStart, 200); // Берем 200 символов после фразы

            // Ищем конец значения (до следующего знака препинания или тега)
            $valueEnd = strpos($remainingText, ';');
            if ($valueEnd === false) {
                $valueEnd = strpos($remainingText, '.');
            }
            if ($valueEnd === false) {
                $valueEnd = strpos($remainingText, '<');
            }
            if ($valueEnd === false) {
                $valueEnd = 200; // Берем все 200 символов
            }

            $rawValue = trim(substr($remainingText, 0, $valueEnd));

            // Разделяем по сепаратору
            $values = explode($separator, $rawValue);
            $values = array_map('trim', $values);
            $values = array_filter($values); // Убираем пустые

            // Ограничиваем количество результатов
            $values = array_slice($values, 0, $maxResults);

            if (!empty($values)) {
                return implode(' | ', $values);
            }
        }

        return null;
    }

    /**
     * Умный парсинг по поисковым фразам
     */
    private function extractBySmartParsing(string $html): ?string
    {
        try {
            $searchContext = $this->search_context ?? '';
            $searchPhrase = $this->search_phrase ?? '';
            $valueSeparator = $this->value_separator ?? '-';
            $resultFormat = $this->result_format ?? '';

            if (empty($searchPhrase)) {
                return null;
            }

            // Определяем команды если нужно
            $teams = null;
            if (in_array($resultFormat, ['team_stats', 'player_events', 'team_names'])) {
                $teams = $this->extractTeams($html);
            }

            switch ($resultFormat) {
                case 'team_stats':
                    return $this->extractTeamStats($html, $searchContext, $searchPhrase, $valueSeparator);

                case 'match_result':
                    return $this->extractMatchResult($html, $searchContext, $searchPhrase, $valueSeparator);

                case 'team_names':
                    return $this->extractTeamNames($html, $searchContext, $searchPhrase, $valueSeparator);

                case 'player_events':
                    return $this->extractPlayerEvents($html, $searchContext, $searchPhrase, $teams);

                default:
                    return $this->extractSimpleValue($html, $searchContext, $searchPhrase, $valueSeparator);
            }
        } catch (\Exception $e) {
            \Log::warning('Error in smart parsing: ' . $e->getMessage(), [
                'field_id' => $this->id,
                'field_name' => $this->name
            ]);
            return null;
        }
    }

    /**
     * Извлечение статистики команд
     */
    private function extractTeamStats(string $html, string $context, string $phrase, string $separator): string
    {
        $value = $this->extractSimpleValue($html, $context, $phrase, $separator);
        if (empty($value)) {
            return '';
        }

        // Разделяем значения и форматируем
        $values = explode($separator, $value);
        $values = array_map('trim', $values);
        $values = array_filter($values);

        return implode(' | ', $values);
    }

    /**
     * Форматирование значения статистики команд для событий команд
     */
    private function formatTeamStatsValue(string $value): array
    {
        if (empty($value)) {
            return ['value1' => '', 'value2' => ''];
        }

        // Разделяем значения по разделителю
        $values = explode(' | ', $value);
        $values = array_map('trim', $values);
        $values = array_filter($values);

        // Берем первые два значения
        $value1 = $values[0] ?? '';
        $value2 = $values[1] ?? '';

        return [
            'value1' => $value1,
            'value2' => $value2
        ];
    }

    /**
     * Форматирование сырого значения для событий команд
     */
    private function formatRawValueForTeamEvents(string $value): array
    {
        if (empty($value)) {
            return ['value1' => '', 'value2' => ''];
        }

        // Пытаемся найти разделитель в значении
        $separators = ['-', ':', '|', '–', '—'];
        $separator = null;
        $separatedValue = null;

        foreach ($separators as $sep) {
            if (strpos($value, $sep) !== false) {
                $separator = $sep;
                $separatedValue = explode($sep, $value);
                break;
            }
        }

        if ($separatedValue && count($separatedValue) >= 2) {
            $value1 = trim($separatedValue[0]);
            $value2 = trim($separatedValue[1]);
        } else {
            // Если разделитель не найден, пытаемся разделить по пробелам
            $parts = preg_split('/\s+/', trim($value));
            $value1 = $parts[0] ?? '';
            $value2 = $parts[1] ?? '';
        }

        return [
            'value1' => $value1,
            'value2' => $value2
        ];
    }

    /**
     * Извлечение результата матча
     */
    private function extractMatchResult(string $html, string $context, string $phrase, string $separator): string
    {
        $value = $this->extractSimpleValue($html, $context, $phrase, $separator);
        if (empty($value)) {
            return '';
        }

        // Преобразуем формат (например, "4 – 3" в "4:3")
        $value = str_replace(['–', '-', ' '], ':', $value);
        $value = preg_replace('/:+/', ':', $value); // Убираем множественные двоеточия

        return $value;
    }

    /**
     * Извлечение названий команд
     */
    private function extractTeamNames(string $html, string $context, string $phrase, string $separator): string
    {
        $value = $this->extractSimpleValue($html, $context, $phrase, $separator);
        if (empty($value)) {
            return '';
        }

        // Разделяем названия команд
        $teams = explode($separator, $value);
        $teams = array_map('trim', $teams);
        $teams = array_filter($teams);

        return implode(' | ', $teams);
    }

    /**
     * Извлечение событий игроков
     */
    private function extractPlayerEvents(string $html, string $context, string $phrase, ?array $teams): string
    {
        // Ищем все строки с событием
        $lines = explode("\n", $html);
        $events = [];

        foreach ($lines as $line) {
            if (stripos($line, $phrase) !== false) {
                $event = $this->parsePlayerEvent($line, $teams);
                if ($event) {
                    $events[] = $event;
                }
            }
        }

        if (empty($events)) {
            return '';
        }

        return json_encode($events, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Парсинг события игрока
     */
    private function parsePlayerEvent(string $line, ?array $teams): ?array
    {
        // Извлекаем время (формат MM:SS)
        if (preg_match('/(\d{1,2}:\d{2})/', $line, $timeMatches)) {
            $time = $timeMatches[1];
        } else {
            $time = '';
        }

        // Извлекаем игрока (после точки или скобки)
        if (preg_match('/[\.\)]\s*(\d+\.\s*[А-Яа-я\s]+)/u', $line, $playerMatches)) {
            $player = trim($playerMatches[1]);
        } else {
            $player = '';
        }

        // Определяем команду
        $team = '';
        if ($teams) {
            foreach ($teams as $teamName) {
                if (stripos($line, $teamName) !== false) {
                    $team = $teamName;
                    break;
                }
            }
        }

        if (empty($time) && empty($player)) {
            return null;
        }

        return [
            'team' => $team,
            'player' => $player,
            'min' => $time,
            'event' => trim($line)
        ];
    }

    /**
     * Извлечение команд из HTML
     */
    private function extractTeams(string $html): array
    {
        $teamIdentification = $this->team_identification ?? [];
        $searchPhrase = $teamIdentification['search_phrase'] ?? '';
        $teamSeparator = $teamIdentification['team_separator'] ?? '-';

        if (empty($searchPhrase)) {
            return [];
        }

        $value = $this->extractSimpleValue($html, $searchPhrase, ':', $teamSeparator);
        if (empty($value)) {
            return [];
        }

        $teams = explode($teamSeparator, $value);
        $teams = array_map('trim', $teams);
        $teams = array_filter($teams);

        return $teams;
    }

    /**
     * Простое извлечение значения
     */
    private function extractSimpleValue(string $html, string $context, string $phrase, string $separator): string
    {
        // Ищем контекст
        $contextStart = 0;
        if (!empty($context)) {
            $contextPos = stripos($html, $context);
            if ($contextPos === false) {
                return ''; // Контекст не найден
            }
            $contextStart = $contextPos;
        }

        // Ищем поисковую фразу в контексте
        $searchPos = stripos(substr($html, $contextStart), $phrase);
        if ($searchPos === false) {
            return ''; // Поисковая фраза не найдена
        }

        $searchPos += $contextStart;
        $valueStart = $searchPos + strlen($phrase);

        // Извлекаем текст после поисковой фразы
        $remainingText = substr($html, $valueStart, 200);

        // Ищем конец значения
        $valueEnd = strpos($remainingText, ';');
        if ($valueEnd === false) {
            $valueEnd = strpos($remainingText, '.');
        }
        if ($valueEnd === false) {
            $valueEnd = strpos($remainingText, '<');
        }
        if ($valueEnd === false) {
            $valueEnd = 200;
        }

        return trim(substr($remainingText, 0, $valueEnd));
    }
}
