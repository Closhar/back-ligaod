<?php

require_once 'vendor/autoload.php';

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Тестирование API сезонов игрока...\n\n";

try {
    $personId = 40; // ID игрока Козловская Алена Дмитриевна

    // Тестируем метод getPersonSeasons
    $controller = new \App\Http\Controllers\PlayerStatisticsController();
    $response = $controller->getPersonSeasons($personId);

    echo "=== ОТВЕТ API ===\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
