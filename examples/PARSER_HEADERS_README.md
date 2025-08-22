# Настройка заголовков для парсера

## Проблема
Многие сайты блокируют запросы от серверов (антибот защита), возвращая HTTP 403 ошибку.

## Решение
Добавлено поле `headers` в таблицу `parser_templates` для настройки пользовательских заголовков HTTP запросов.

## Миграция
```bash
php artisan migrate
```

## Использование

### 1. Создание шаблона с заголовками
```json
{
  "name": "KHL Parser",
  "description": "Парсер для сайта KHL",
  "url_pattern": "/online/\\d+\\.html/",
  "headers": {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
    "Accept-Language": "ru-RU,ru;q=0.9,en;q=0.8",
    "Referer": "https://www.google.com/"
  }
}
```

### 2. Стандартные заголовки
Если заголовки не указаны, используются стандартные заголовки для обхода антибот защиты.

### 3. Пример для KHL
Смотрите файл `khl_parser_headers.json` для примера заголовков, которые работают с сайтом KHL.

## Важные заголовки

- **User-Agent** - Имитирует браузер
- **Accept** - Указывает типы контента
- **Accept-Language** - Язык запроса
- **Referer** - Ссылается на поисковую систему
- **Sec-Fetch-*** - Современные заголовки безопасности

## Тестирование
Используйте метод `test` для проверки работы шаблона перед парсингом.
