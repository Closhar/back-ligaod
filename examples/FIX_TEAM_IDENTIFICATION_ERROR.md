# Исправление ошибки валидации team_identification

## Проблема
```
"Значение поля fields.13.team_identification должно быть массивом."
```

## Причина
Поле `team_identification` передается как строка, а валидация Laravel ожидает массив.

## Что исправлено

### 1. Frontend (pages/parser/[id]/edit.vue)
Добавлена обработка `team_identification` перед отправкой:
```javascript
// Обрабатываем team_identification - убеждаемся что это объект
let teamIdentification = null
if (field.team_identification) {
  if (typeof field.team_identification === 'string') {
    try {
      teamIdentification = JSON.parse(field.team_identification)
    } catch (e) {
      // Если не удалось распарсить JSON, создаем пустой объект
      teamIdentification = {
        search_phrase: '',
        team_separator: '-'
      }
    }
  } else if (typeof field.team_identification === 'object') {
    teamIdentification = field.team_identification
  }
}
```

### 2. Backend (app/Http/Controllers/ParserController.php)
Добавлена обработка в методах `store()` и `update()`:
```php
// Обрабатываем team_identification - убеждаемся что это массив
$teamIdentification = null;
if (isset($fieldData['team_identification'])) {
    if (is_array($fieldData['team_identification'])) {
        $teamIdentification = $fieldData['team_identification'];
    } elseif (is_string($fieldData['team_identification'])) {
        $decoded = json_decode($fieldData['team_identification'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $teamIdentification = $decoded;
        }
    }
}
```

## Как это работает

1. **Frontend**: Проверяет тип `team_identification` и преобразует строку в объект
2. **Backend**: Дополнительно проверяет и обрабатывает поле перед сохранением
3. **Валидация**: Теперь всегда получает корректный массив или null

## Структура team_identification

```json
{
  "search_phrase": "Игра №",
  "team_separator": "-"
}
```

## Что нужно сделать

1. **Обновить файлы** (уже сделано)
2. **Перезапустить сервер** (если необходимо)
3. **Попробовать сохранить шаблон** снова

## Ожидаемый результат

- Ошибка валидации `team_identification` больше не появляется
- Поля умного парсинга корректно сохраняются
- Система работает стабильно

## Если проблема остается

1. Проверьте консоль браузера на ошибки JavaScript
2. Убедитесь, что все файлы обновлены
3. Проверьте, что сервер перезапущен
4. Очистите кэш браузера
