# Инструкция по обновлению серверного бэкенда

## Проблема
Блок "ИНФОРМАЦИЯ О СОБЫТИИ" не отображается на фронтенде, потому что поле `report` не передается в API ответе.

## Решение
Добавлен `select` с полем `report` в метод `show` контроллера `ApiEventController`.

## Шаги для обновления сервера

### 1. Подключение к серверу
```bash
ssh user@server
cd /path/to/p.sportrep.ru
```

### 2. Получение изменений из Git
```bash
git pull origin main
```

### 3. Обновление зависимостей (если нужно)
```bash
composer install --no-dev --optimize-autoloader
```

### 4. Очистка кэша Laravel
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 5. Проверка изменений
Файл `app/Http/Controllers/Api/ApiEventController.php` должен содержать:
```php
$event = Event::select([
    'id', 'title', 'date_from', 'date_to', 'result', 'result_dop', 'image',
    'competition_id', 'arena_id', 'club1_id', 'club2_id', 'region_id',
    'is_active', 'event_name', 'series_id', 'series_count', 'about',
    'tickets', 'report', 'free_tickets', 'gallery_id', 'image', 'slug'
])->with([
```

### 6. Тестирование API
```bash
curl "https://p.sportrep.ru/api/v1/events/1?include=lineups" | jq '.[0].report'
```

### 7. Перезапуск веб-сервера (если нужно)
```bash
sudo systemctl restart nginx
sudo systemctl restart php-fpm
```

## Ожидаемый результат
После обновления блок "ИНФОРМАЦИЯ О СОБЫТИИ" должен отображаться на странице матча для событий, у которых заполнено поле `report`.

## Проверка
1. Откройте страницу матча: `https://sportrep.ru/clubs/zenit/matches/1`
2. Проверьте, что блок "ИНФОРМАЦИЯ О СОБЫТИИ" отображается
3. Проверьте API напрямую: `https://p.sportrep.ru/api/v1/events/1?include=lineups` 