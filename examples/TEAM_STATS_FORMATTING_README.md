# Изменение формата вывода статистики команд

## Описание проблемы

Ранее для событий команд (`target_table === 'event_teams'`) возвращалось одно поле `value` со значениями, разделенными символом `|` (например: "59 | 50").

**Пример старого формата:**
```json
{
  "value": "59 | 50"
}
```

## Новое решение

Теперь для событий команд возвращается массив с двумя отдельными полями:

**Новый формат:**
```json
{
  "value1": "59",
  "value2": "50"
}
```

## Технические изменения

### 1. Изменен метод `extractValue()` в `ParserField.php`

Добавлена логика для определения типа поля и применения соответствующего форматирования:

```php
// Для событий команд возвращаем массив с двумя значениями
if ($this->target_table === 'event_teams' && $this->result_format === 'team_stats') {
    return $this->formatTeamStatsValue($value);
}

// Для событий команд возвращаем массив с двумя значениями
if ($this->target_table === 'event_teams') {
    return $this->formatRawValueForTeamEvents($value);
}
```

### 2. Добавлен метод `formatTeamStatsValue()`

Форматирует результат умного парсинга для событий команд:

```php
private function formatTeamStatsValue(string $value): array
{
    if (empty($value)) {
        return ['value1' => '', 'value2' => ''];
    }

    // Разделяем значения по разделителю |
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
```

### 3. Добавлен метод `formatRawValueForTeamEvents()`

Форматирует сырые значения для событий команд, автоматически определяя разделитель:

```php
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
```

## Поддерживаемые разделители

Автоматически определяются следующие разделители:
- `-` (дефис)
- `:` (двоеточие)
- `|` (вертикальная черта)
- `–` (тире)
- `—` (длинное тире)
- Пробелы (если другие разделители не найдены)

## Примеры работы

### Входные данные: "59-50"
**Результат:**
```json
{
  "value1": "59",
  "value2": "50"
}
```

### Входные данные: "59:50"
**Результат:**
```json
{
  "value1": "59",
  "value2": "50"
}
```

### Входные данные: "59 | 50"
**Результат:**
```json
{
  "value1": "59",
  "value2": "50"
}
```

### Входные данные: "59 50"
**Результат:**
```json
{
  "value1": "59",
  "value2": "50"
}
```

## Тестирование

Для тестирования создан файл `test_team_stats_formatting.php`:

```bash
php examples/test_team_stats_formatting.php
```

## Обратная совместимость

Изменения применяются только к полям с `target_table === 'event_teams'`. Все остальные поля продолжают работать как раньше.

## Применение изменений

1. Сохраните изменения в `ParserField.php`
2. Протестируйте парсинг событий команд
3. Убедитесь, что в JSON теперь возвращаются поля `value1` и `value2` вместо `value`
