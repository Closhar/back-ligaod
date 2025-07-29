<?php

// Тестовый файл для проверки API матчей
// Запуск: php test_match_api.php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Event;

echo "=== Тест API матчей ===\n\n";

// Получаем первый матч для тестирования
$match = Event::with(['home_team', 'away_team', 'competition', 'arena', 'sport'])->first();

if ($match) {
    echo "Найден матч ID: {$match->id}\n";
    echo "Название: {$match->title}\n";
    echo "Домашняя команда: {$match->home_team->title}\n";
    echo "Гостевая команда: {$match->away_team->title}\n";
    echo "Дата: {$match->date}\n";
    echo "Счет: {$match->home_score} - {$match->away_score}\n\n";

    // Тестируем API endpoint
    $url = "http://localhost/api/matches/{$match->id}";
    echo "Тестируем API endpoint: {$url}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP код: {$httpCode}\n";
    echo "Ответ:\n";
    echo $response . "\n";

} else {
    echo "Матчи не найдены в базе данных\n";
}

echo "\n=== Тест завершен ===\n";
