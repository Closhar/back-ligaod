# Пример конфигурации поля для умного извлечения данных

## Проблема
Сейчас парсер извлекает весь HTML страницы для полей с селектором `body`, но нужно извлекать конкретные значения по поисковым фразам.

## Решение
Используем новые правила извлечения типа `search_phrase` для точного извлечения данных.

## Пример конфигурации поля "Броски"

```json
{
  "name": "shots",
  "selector": "body",
  "selector_type": "css",
  "target_table": "event_teams",
  "target_field": "shots",
  "extraction_rules": [
    {
      "type": "search_phrase",
      "phrase": "Броски:",
      "context": "Статистика матча:",
      "separator": "-",
      "max_results": 2
    }
  ]
}
```

## Параметры правила `search_phrase`

- **`phrase`** - поисковая фраза (например, "Броски:")
- **`context`** - контекст поиска (например, "Статистика матча:")
- **`separator`** - разделитель значений (например, "-" для "59-50")
- **`max_results`** - максимальное количество извлекаемых значений

## Примеры других полей

### Поле "Броски в створ"
```json
{
  "name": "shots_on_target",
  "selector": "body",
  "selector_type": "css",
  "target_table": "event_teams",
  "target_field": "shots_on_target",
  "extraction_rules": [
    {
      "type": "search_phrase",
      "phrase": "Броски в створ:",
      "context": "Статистика матча:",
      "separator": "-",
      "max_results": 2
    }
  ]
}
```

### Поле "Голы"
```json
{
  "name": "goals",
  "selector": "body",
  "selector_type": "css",
  "target_table": "event_teams",
  "target_field": "goals",
  "extraction_rules": [
    {
      "type": "search_phrase",
      "phrase": "Голы:",
      "context": "Статистика матча:",
      "separator": "-",
      "max_results": 2
    }
  ]
}
```

### Поле "Вбрасывания"
```json
{
  "name": "faceoffs",
  "selector": "body",
  "selector_type": "css",
  "target_table": "event_teams",
  "target_field": "faceoffs",
  "extraction_rules": [
    {
      "type": "search_phrase",
      "phrase": "Вбрасывания:",
      "context": "Статистика матча:",
      "separator": "-",
      "max_results": 2
    }
  ]
}
```

## Ожидаемый результат

Вместо извлечения всего HTML:
```
"value": "Предсезонные матчи МХЛ. Игра № 44 21 авг 2025: МХК Крылья Советов-ХК Капитан..."
```

Будет извлекаться конкретное значение:
```
"value": "59 | 50"
```

## Как это работает

1. **Поиск контекста**: Находит "Статистика матча:" в HTML
2. **Поиск фразы**: В контексте ищет "Броски:"
3. **Извлечение значения**: Берет текст после фразы до разделителя
4. **Разделение**: Разбивает "59-50" на ["59", "50"]
5. **Форматирование**: Возвращает "59 | 50"

## Обновление существующих полей

Для обновления существующих полей в базе данных, добавьте `extraction_rules` в JSON формате через админку или миграцию.
