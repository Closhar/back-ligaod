# Исправление проблем с API клубов на сервере

## Проблема
POST запрос к `/api/clubs` возвращает Internal Server Error (500)

## Диагностика

### 1. Проверка логов сервера
```bash
# Проверьте логи Laravel
tail -f /path/to/laravel/storage/logs/laravel.log

# Проверьте логи веб-сервера
tail -f /var/log/nginx/error.log
tail -f /var/log/apache2/error.log
```

### 2. Проверка настроек PHP
```bash
# Проверьте текущие настройки PHP
php -i | grep memory
php -i | grep max_execution_time
php -i | grep post_max_size
```

### 3. Увеличение лимитов памяти

#### Для Apache (.htaccess)
```apache
php_value memory_limit 1024M
php_value max_execution_time 300
php_value max_input_time 300
php_value post_max_size 20M
php_value upload_max_filesize 20M
```

#### Для Nginx (php.ini)
```ini
memory_limit = 1024M
max_execution_time = 300
max_input_time = 300
post_max_size = 20M
upload_max_filesize = 20M
```

### 4. Проверка базы данных
```bash
# Проверьте подключение к БД
php artisan tinker
>>> DB::connection()->getPdo()
>>> App\Models\City::count()
>>> App\Models\Sport::count()
>>> App\Models\Gender::count()
```

### 5. Очистка кэша
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 6. Проверка прав доступа
```bash
# Убедитесь, что папки доступны для записи
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/
```

### 7. Тестирование API

#### Создайте тестовый скрипт на сервере:
```php
<?php
// test_club_api.php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();
    
    if (!$city || !$sport || !$gender) {
        echo "❌ Недостаточно данных\n";
        exit;
    }
    
    $clubData = [
        'title' => 'Тестовый клуб ' . time(),
        'title_short' => 'ТК',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];
    
    $club = \App\Models\Club::create($clubData);
    echo "✅ Клуб создан с ID: {$club->id}\n";
    
    $club->delete();
    echo "✅ Тестовый клуб удален\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}
```

#### Запустите тест:
```bash
php test_club_api.php
```

### 8. Проверка middleware

Убедитесь, что CORS middleware работает корректно:
```php
// app/Http/Middleware/CorsMiddleware.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);

    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');

    if ($request->isMethod('OPTIONS')) {
        $response->setStatusCode(200);
        $response->setContent('');
    }

    return $response;
}
```

### 9. Мониторинг ресурсов

#### Проверьте использование памяти:
```bash
# Мониторинг памяти PHP процессов
ps aux | grep php
free -h
```

#### Проверьте логи ошибок в реальном времени:
```bash
# Следите за логами во время выполнения запроса
tail -f storage/logs/laravel.log &
```

### 10. Временное решение

Если проблема с памятью критична, можно временно увеличить лимиты:
```bash
# Временное увеличение лимитов через .htaccess
php_value memory_limit 2048M
php_value max_execution_time 600
```

## Рекомендации

1. **Увеличьте лимиты памяти** до 1024M или 2048M
2. **Проверьте логи** для точной диагностики
3. **Очистите кэш** Laravel
4. **Проверьте права доступа** к папкам
5. **Мониторьте ресурсы** сервера во время запросов

## Контакты для поддержки

Если проблема не решается, предоставьте:
- Логи ошибок
- Результаты диагностики
- Информацию о сервере (ОС, версии PHP/Laravel)

## Финальные шаги для исправления

### Шаг 1: Проверьте логи Laravel на сервере
```bash
tail -f /var/www/p.sportrep.loc/storage/logs/laravel.log
```

### Шаг 2: Увеличьте лимиты памяти в .htaccess
```apache
# Добавьте в .htaccess в корне проекта
php_value memory_limit 1024M
php_value max_execution_time 300
php_value max_input_time 300
php_value post_max_size 20M
php_value upload_max_filesize 20M
```

### Шаг 3: Очистите кэш Laravel
```bash
cd /var/www/p.sportrep.loc
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Шаг 4: Проверьте права доступа
```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/
```

### Шаг 5: Запустите диагностический скрипт
Скопируйте файл `diagnose_api.php` на сервер и запустите:
```bash
php diagnose_api.php
```

### Шаг 6: Проверьте API после исправлений
После внесения изменений протестируйте API снова через фронтенд или curl.

## Мониторинг

После внесения изменений следите за логами:
```bash
tail -f /var/www/p.sportrep.loc/storage/logs/laravel.log
tail -f /var/log/nginx/error.log
``` 
