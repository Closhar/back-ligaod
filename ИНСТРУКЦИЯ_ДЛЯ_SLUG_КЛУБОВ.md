# Инструкция для применения изменений в модели Club

## Изменения в файлах

### 1. `app/Models/Club.php`

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

### 2. `app/Http/Controllers/Api/ApiClubController.php`

Исправлен метод `show()`:

-   Переименован параметр `$gender` в `$club`
-   Добавлено явное включение `slug` в результат

```php
public function show(Club $club, $slug): array
{
    // ... код ...

    // Явно включаем slug в результат
    $clubArr = $club->toArray();
    $clubArr['slug'] = $club->slug; // Убеждаемся, что slug включен
    $clubArr['active_memberships'] = $activeMemberships->toArray();
    return $clubArr;
}
```

### 3. `app/Http/Controllers/Api/PersonController.php`

Добавлено поле `slug` в select для клубов:

```php
'activeClubMemberships.club' => function ($query) {
    $query->select(['id', 'title', 'slug', 'image', 'city_id', 'sport_id', 'gender_id', 'full_info']);
},
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

Также проверить API персон:

```bash
curl -X GET "https://p.sportrep.ru/api/v1/people/48" -H "Accept: application/json"
```

В `active_club_memberships[0].club` должно появиться поле `slug` и `full_info`.
