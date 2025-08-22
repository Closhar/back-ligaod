# Исправление ошибки валидации team_identification

## Проблема 1: Валидация
```
"Значение поля fields.13.team_identification должно быть массивом."
```

## Проблема 2: Сохранение в БД
```
"Array to string conversion (Connection: mysql, SQL: insert into `parser_fields` ...)"
```

## Причины
1. **Валидация**: Поле `team_identification` передавалось как строка, а валидация Laravel ожидала массив
2. **Сохранение**: Поле `team_identification` передавалось как массив, а база данных ожидала JSON строку

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

**Обработка входящих данных:**
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

**Сохранение в БД (КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ):**
```php
'team_identification' => $teamIdentification ? json_encode($teamIdentification) : null,
```

## Как это работает

1. **Frontend**: Проверяет тип и преобразует строку в объект
2. **Backend**: 
   - Принимает данные и обрабатывает `team_identification`
   - **Преобразует массив в JSON строку** перед сохранением в БД
3. **Валидация**: Теперь всегда получает корректный массив или null
4. **База данных**: Получает JSON строку, а не массив

## Структура team_identification

**В форме (массив):**
```php
[
  'search_phrase' => 'Игра №',
  'team_separator' => '-'
]
```

**В базе данных (JSON строка):**
```json
{"search_phrase":"Игра №","team_separator":"-"}
```

## Что нужно сделать

1. **Обновить файлы** (уже сделано)
2. **Перезапустить сервер** (если необходимо)
3. **Попробовать сохранить шаблон** снова

## Ожидаемый результат

- Ошибка валидации `team_identification` больше не появляется
- Ошибка `Array to string conversion` больше не появляется
- Поля умного парсинга корректно сохраняются
- Система работает стабильно

## Если проблема остается

1. Проверьте консоль браузера на ошибки JavaScript
2. Убедитесь, что все файлы обновлены
3. Проверьте, что сервер перезапущен
4. Очистите кэш браузера
5. Проверьте логи Laravel на ошибки PHP

## Техническая деталь

Проблема была в том, что:
- **Eloquent** автоматически преобразует JSON поля в массивы при загрузке
- Но при сохранении нужно явно преобразовать массив обратно в JSON строку
- Использование `json_encode()` решает эту проблему
