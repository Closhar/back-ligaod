# Инструкция для применения изменений в модели Club

## Изменения в файле `app/Models/Club.php`

Добавлено поле `slug` в массив `$fillable`:

```php
protected $fillable = [
    'title',
    'slug',
    'image',
    'image_bg',
    'city_id',
    'sport_id',
    'gender_id',
    'age_id',
    'rating_region_id',
    'gallery_id',
    'description',
    'tlgs_to_parse'
];
```

## Команды для применения на сервере

1. Очистить кэш Laravel:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

2. Перезапустить веб-сервер (если используется Apache/Nginx):

```bash
sudo systemctl restart apache2
# или
sudo systemctl restart nginx
```

## Проверка

После применения изменений поле `slug` должно появиться в API ответах для клубов.

Для проверки можно выполнить:

```bash
curl -X GET "https://p.sportrep.ru/api/v1/clubs/292" -H "Accept: application/json"
```

Теперь в ответе должно быть поле `slug` для клуба "Рыси".
