<?php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Глубокая диагностика сервера\n";
echo "===============================\n\n";

// 1. Проверка PHP настроек
echo "1. PHP настройки:\n";
echo "   memory_limit: " . ini_get('memory_limit') . "\n";
echo "   max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "   post_max_size: " . ini_get('post_max_size') . "\n";
echo "   upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   max_input_vars: " . ini_get('max_input_vars') . "\n";
echo "   display_errors: " . ini_get('display_errors') . "\n";
echo "   log_errors: " . ini_get('log_errors') . "\n";
echo "   error_reporting: " . ini_get('error_reporting') . "\n\n";

// 2. Проверка окружения
echo "2. Окружение:\n";
echo "   APP_ENV: " . config('app.env') . "\n";
echo "   APP_DEBUG: " . (config('app.debug') ? 'true' : 'false') . "\n";
echo "   APP_URL: " . config('app.url') . "\n\n";

// 3. Проверка базы данных
echo "3. База данных:\n";
try {
    $pdo = \DB::connection()->getPdo();
    echo "   ✅ Подключение к БД успешно\n";

    // Проверяем таблицы
    $tables = ['clubs', 'cities', 'sports', 'genders'];
    foreach ($tables as $table) {
        $count = \DB::table($table)->count();
        echo "   {$table}: {$count} записей\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка подключения к БД: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Проверка моделей
echo "4. Проверка моделей:\n";
try {
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();

    if ($city && $sport && $gender) {
        echo "   ✅ Все необходимые модели доступны\n";
        echo "   Город: {$city->title}\n";
        echo "   Спорт: {$sport->title}\n";
        echo "   Пол: {$gender->title}\n";
    } else {
        echo "   ❌ Не все модели доступны\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка при проверке моделей: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Проверка контроллера
echo "5. Проверка контроллера:\n";
try {
    $controller = new \App\Http\Controllers\Admin\Data\ClubController();
    echo "   ✅ Контроллер создан успешно\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка создания контроллера: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Тест создания клуба с детальным логированием
echo "6. Тест создания клуба:\n";
try {
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();

    if (!$city || !$sport || !$gender) {
        echo "   ❌ Недостаточно данных\n";
        exit(1);
    }

    $clubData = [
        'title' => 'Диагностический клуб ' . time(),
        'title_short' => 'ДК',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];

    echo "   📝 Данные для создания:\n";
    print_r($clubData);
    echo "\n";

    echo "   🚀 Создаем клуб...\n";
    $club = \App\Models\Club::create($clubData);
    echo "   ✅ Клуб создан с ID: {$club->id}\n";

    // Удаляем тестовый клуб
    $club->delete();
    echo "   ✅ Тестовый клуб удален\n";

} catch (Exception $e) {
    echo "   ❌ Ошибка создания клуба: " . $e->getMessage() . "\n";
    echo "   📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// 7. Тест API запроса
echo "7. Тест API запроса:\n";
try {
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();

    $testData = [
        'title' => 'API Тест ' . time(),
        'title_short' => 'АТ',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];

    $request = new \Illuminate\Http\Request();
    $request->merge($testData);

    $controller = new \App\Http\Controllers\Admin\Data\ClubController();
    $response = $controller->store($request);

    echo "   📊 Результат API:\n";
    echo "   Status: " . $response->getStatusCode() . "\n";
    echo "   Content: " . substr($response->getContent(), 0, 200) . "...\n";

    if ($response->getStatusCode() === 201) {
        echo "   ✅ API запрос успешен!\n";

        // Удаляем тестовый клуб
        $clubData = json_decode($response->getContent(), true);
        $clubId = $clubData['id'];
        $club = \App\Models\Club::find($clubId);
        if ($club) {
            $club->delete();
            echo "   ✅ Тестовый клуб удален\n";
        }
    } else {
        echo "   ❌ API запрос завершился с ошибкой\n";
    }

} catch (Exception $e) {
    echo "   ❌ Ошибка API запроса: " . $e->getMessage() . "\n";
    echo "   📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// 8. Проверка логов
echo "8. Проверка логов:\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    echo "   Размер лог-файла: " . number_format($logSize / 1024 / 1024, 2) . " MB\n";

    if ($logSize > 50 * 1024 * 1024) {
        echo "   ⚠️ Лог-файл слишком большой\n";
    }

    // Показываем последние ошибки
    $lastLines = file($logFile);
    $lastLines = array_slice($lastLines, -20);
    echo "   Последние записи в логе:\n";
    foreach ($lastLines as $line) {
        if (strpos($line, 'ERROR') !== false || strpos($line, 'Exception') !== false) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "   ❌ Лог-файл не найден\n";
}
echo "\n";

// 9. Проверка прав доступа
echo "9. Проверка прав доступа:\n";
$paths = [
    'storage/logs' => 'storage/logs',
    'storage/framework' => 'storage/framework',
    'bootstrap/cache' => 'bootstrap/cache'
];

foreach ($paths as $name => $path) {
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "   {$name}: {$perms}\n";
    } else {
        echo "   {$name}: не найден\n";
    }
}
echo "\n";

// 10. Проверка маршрутов
echo "10. Проверка маршрутов:\n";
try {
    $routes = \Route::getRoutes();
    $clubRoutes = [];

    foreach ($routes as $route) {
        if (strpos($route->uri(), 'clubs') !== false) {
            $clubRoutes[] = $route->methods()[0] . ' ' . $route->uri();
        }
    }

    if (!empty($clubRoutes)) {
        echo "   ✅ Маршруты клубов найдены:\n";
        foreach ($clubRoutes as $route) {
            echo "   " . $route . "\n";
        }
    } else {
        echo "   ❌ Маршруты клубов не найдены\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки маршрутов: " . $e->getMessage() . "\n";
}
echo "\n";

echo "🏁 Диагностика завершена\n";
