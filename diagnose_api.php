<?php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Диагностика API проблем\n";
echo "========================\n\n";

// 1. Проверка памяти
echo "1. Проверка памяти:\n";
echo "   memory_limit: " . ini_get('memory_limit') . "\n";
echo "   max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "   max_input_time: " . ini_get('max_input_time') . "\n";
echo "   post_max_size: " . ini_get('post_max_size') . "\n";
echo "   upload_max_filesize: " . ini_get('upload_max_filesize') . "\n\n";

// 2. Проверка базы данных
echo "2. Проверка базы данных:\n";
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

// 3. Проверка моделей
echo "3. Проверка моделей:\n";
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

// 4. Проверка контроллера
echo "4. Проверка контроллера:\n";
try {
    $controller = new \App\Http\Controllers\Admin\Data\ClubController();
    echo "   ✅ Контроллер создан успешно\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка создания контроллера: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Проверка валидации
echo "5. Проверка валидации:\n";
try {
    $testData = [
        'title' => 'Тестовый клуб',
        'title_short' => 'ТК',
        'city_id' => 1,
        'sport_id' => 1,
        'gender_id' => 1,
        'is_alien' => false
    ];
    
    $request = new \Illuminate\Http\Request();
    $request->merge($testData);
    
    $validator = \Validator::make($testData, [
        'title' => 'required|string|max:255',
        'title_short' => 'nullable|string|max:100',
        'city_id' => 'nullable|exists:cities,id',
        'sport_id' => 'required|exists:sports,id',
        'gender_id' => 'required|exists:genders,id',
        'is_alien' => 'boolean'
    ]);
    
    if ($validator->passes()) {
        echo "   ✅ Валидация проходит успешно\n";
    } else {
        echo "   ❌ Ошибки валидации:\n";
        foreach ($validator->errors()->all() as $error) {
            echo "      - {$error}\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка валидации: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Проверка создания клуба
echo "6. Тест создания клуба:\n";
try {
    $clubData = [
        'title' => 'Диагностический клуб ' . time(),
        'title_short' => 'ДК',
        'city_id' => 1,
        'sport_id' => 1,
        'gender_id' => 1,
        'is_alien' => false
    ];
    
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

// 7. Проверка логов
echo "7. Проверка логов:\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    echo "   Размер лог-файла: " . number_format($logSize / 1024 / 1024, 2) . " MB\n";
    
    if ($logSize > 50 * 1024 * 1024) { // 50MB
        echo "   ⚠️ Лог-файл слишком большой, рекомендуется очистить\n";
    }
} else {
    echo "   ❌ Лог-файл не найден\n";
}
echo "\n";

echo "🏁 Диагностика завершена\n"; 