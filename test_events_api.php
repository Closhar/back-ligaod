<?php

require_once 'vendor/autoload.php';

use Carbon\Carbon;

echo "=== Тест API событий ===\n";

// Получаем текущую дату в UTC
$todayUTC = Carbon::now('UTC')->toDateString();
echo "Сегодняшняя дата (UTC): {$todayUTC}\n";

// Получаем текущую дату в Moscow
$todayMoscow = Carbon::now('Europe/Moscow')->toDateString();
echo "Сегодняшняя дата (Moscow): {$todayMoscow}\n";

// Подключаемся к базе данных
try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') .
            ';dbname=' . env('DB_DATABASE', 'sportrep') .
            ';charset=utf8mb4',
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );

    echo "Подключение к БД успешно\n";

    // Проверяем события на сегодня
    $stmt = $pdo->prepare("
        SELECT id, title, date_from, is_active, region_id 
        FROM events 
        WHERE DATE(date_from) = ? 
        AND is_active = 1 
        LIMIT 5
    ");

    $stmt->execute([$todayUTC]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Событий на сегодня ({$todayUTC}): " . count($events) . "\n";

    foreach ($events as $event) {
        echo "- ID: {$event['id']}, Название: {$event['title']}, Дата: {$event['date_from']}, Регион: {$event['region_id']}\n";
    }

    // Проверяем события на завтра
    $tomorrowUTC = Carbon::now('UTC')->addDay()->toDateString();
    $stmt->execute([$tomorrowUTC]);
    $eventsTomorrow = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Событий на завтра ({$tomorrowUTC}): " . count($eventsTomorrow) . "\n";

    foreach ($eventsTomorrow as $event) {
        echo "- ID: {$event['id']}, Название: {$event['title']}, Дата: {$event['date_from']}, Регион: {$event['region_id']}\n";
    }
} catch (Exception $e) {
    echo "Ошибка подключения к БД: " . $e->getMessage() . "\n";
}

echo "=== Конец теста ===\n";
