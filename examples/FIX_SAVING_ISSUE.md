# Исправление проблемы с сохранением полей умного парсинга

## Проблема
На странице редактирования шаблона данные из блока "Умный парсинг" не сохраняются.

## Причина
Новые поля умного парсинга не передавались в бэкенд и не сохранялись в базе данных.

## Что исправлено

### 1. Frontend (pages/parser/[id]/edit.vue)
- Обновлен метод `saveTemplate()` для передачи новых полей
- Добавлена подготовка полей с параметрами умного парсинга
- Поля теперь корректно отправляются в бэкенд

### 2. Backend (app/Http/Controllers/ParserController.php)
- Добавлена валидация для новых полей в методах `store()` и `update()`
- Обновлено создание полей с сохранением новых параметров
- Новые поля теперь корректно сохраняются в базе данных

### 3. Model (app/Models/ParserField.php)
- Исправлена ошибка в методе `extractTeamNames()`
- Добавлены все необходимые методы для умного парсинга

## Новые поля, которые теперь сохраняются

```php
// В методе saveTemplate() на фронтенде
const preparedFields = form.value.fields.map(field => ({
    // ... существующие поля ...
    
    // Новые поля умного парсинга
    search_context: field.search_context || null,
    search_phrase: field.search_phrase || null,
    value_separator: field.value_separator || null,
    result_format: field.result_format || null,
    team_identification: field.team_identification || null
}))
```

```php
// В ParserController на бэкенде
$template->fields()->create([
    // ... существующие поля ...
    
    // Новые поля умного парсинга
    'search_context' => $fieldData['search_context'] ?? null,
    'search_phrase' => $fieldData['search_phrase'] ?? null,
    'value_separator' => $fieldData['value_separator'] ?? null,
    'result_format' => $fieldData['result_format'] ?? null,
    'team_identification' => $fieldData['team_identification'] ?? null,
]);
```

## Валидация новых полей

```php
'fields.*.search_context' => 'nullable|string',
'fields.*.search_phrase' => 'nullable|string',
'fields.*.value_separator' => 'nullable|string',
'fields.*.result_format' => 'nullable|string',
'fields.*.team_identification' => 'nullable|array',
```

## Что нужно сделать

1. **Убедиться, что миграция выполнена**:
   ```bash
   php artisan migrate
   ```

2. **Обновить поля парсера**:
   ```bash
   mysql -u username -p database_name < examples/update_parser_fields_smart_parsing.sql
   ```

3. **Перезапустить сервер** (если необходимо)

4. **Проверить в админке** - новые поля должны сохраняться

## Тестирование

После исправления запустите тест:
```bash
php examples/test_smart_parsing.php
```

## Ожидаемый результат

- Поля "Умный парсинг" корректно сохраняются
- При редактировании шаблона настройки загружаются
- Парсинг работает с новыми параметрами
- Вместо всего HTML извлекаются конкретные данные

## Если проблема остается

1. Проверьте консоль браузера на ошибки JavaScript
2. Проверьте логи Laravel на ошибки PHP
3. Убедитесь, что все файлы обновлены
4. Проверьте, что миграция выполнена успешно

