<?php

require_once 'vendor/autoload.php';

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Тестирование API статистики по сезону...\n\n";

try {
    $personId = 40;
    $seasonId = 1; // 2025

    echo "=== ТЕСТИРОВАНИЕ API ===\n";
    echo "Person ID: {$personId}\n";
    echo "Season ID: {$seasonId}\n\n";

    // Создаем HTTP запрос
    $request = \Illuminate\Http\Request::create(
        "/api/people/{$personId}/statistics/season/{$seasonId}",
        'GET'
    );

    // Получаем роутер
    $router = app('router');

    // Находим роут
    $route = $router->getRoutes()->match($request);

    if (!$route) {
        echo "❌ Роут не найден!\n";
        exit;
    }

    echo "✓ Роут найден: " . $route->getActionName() . "\n";

    // Выполняем запрос
    $response = app()->handle($request);
    $content = $response->getContent();
    $data = json_decode($content, true);

    echo "✓ Статус ответа: " . $response->getStatusCode() . "\n";
    echo "✓ Ответ API:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
