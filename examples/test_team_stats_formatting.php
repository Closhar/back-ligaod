<?php

/**
 * Тест форматирования статистики команд для событий команд
 * 
 * Запуск: php examples/test_team_stats_formatting.php
 */

// Имитируем класс ParserField для тестирования
class TestParserField
{
    public function formatTeamStatsValue(string $value): array
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

    public function formatRawValueForTeamEvents(string $value): array
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
}

// Тестируем
$parser = new TestParserField();

echo "=== Тест форматирования статистики команд ===\n\n";

// Тест 1: Умный парсинг с разделителем |
$testValue1 = "59 | 50";
$result1 = $parser->formatTeamStatsValue($testValue1);
echo "Тест 1 - Умный парсинг '{$testValue1}':\n";
echo "Результат: " . json_encode($result1, JSON_UNESCAPED_UNICODE) . "\n\n";

// Тест 2: Сырое значение с разделителем -
$testValue2 = "59-50";
$result2 = $parser->formatRawValueForTeamEvents($testValue2);
echo "Тест 2 - Сырое значение '{$testValue2}':\n";
echo "Результат: " . json_encode($result2, JSON_UNESCAPED_UNICODE) . "\n\n";

// Тест 3: Сырое значение с разделителем :
$testValue3 = "59:50";
$result3 = $parser->formatRawValueForTeamEvents($testValue3);
echo "Тест 3 - Сырое значение '{$testValue3}':\n";
echo "Результат: " . json_encode($result3, JSON_UNESCAPED_UNICODE) . "\n\n";

// Тест 4: Сырое значение с разделителем –
$testValue4 = "59–50";
$result4 = $parser->formatRawValueForTeamEvents($testValue4);
echo "Тест 4 - Сырое значение '{$testValue4}':\n";
echo "Результат: " . json_encode($result4, JSON_UNESCAPED_UNICODE) . "\n\n";

// Тест 5: Сырое значение с пробелами
$testValue5 = "59 50";
$result5 = $parser->formatRawValueForTeamEvents($testValue5);
echo "Тест 5 - Сырое значение '{$testValue5}':\n";
echo "Результат: " . json_encode($result5, JSON_UNESCAPED_UNICODE) . "\n\n";

// Тест 6: Пустое значение
$testValue6 = "";
$result6 = $parser->formatRawValueForTeamEvents($testValue6);
echo "Тест 6 - Пустое значение '{$testValue6}':\n";
echo "Результат: " . json_encode($result6, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Тест завершен ===\n";
