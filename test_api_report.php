<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Event;

echo "Тестирование API запроса для события ID 1:\n";
echo "==========================================\n\n";

// Симулируем запрос через контроллер
$controller = new \App\Http\Controllers\Api\ApiEventController();
$request = new \Illuminate\Http\Request();
$request->merge(['include' => 'lineups']);

try {
    $result = $controller->show(new Event(), 1);
    $eventData = $result[0] ?? null;

    if ($eventData) {
        echo "API Response для Event ID 1:\n";
        echo "Report field: " . ($eventData['report'] ?? 'NULL') . "\n";
        echo "Report exists: " . (isset($eventData['report']) ? 'YES' : 'NO') . "\n";
        echo "Report is null: " . (is_null($eventData['report']) ? 'YES' : 'NO') . "\n";
        echo "Report length: " . (strlen($eventData['report'] ?? '') ?: 0) . "\n";

        echo "\nВсе поля события:\n";
        foreach ($eventData as $key => $value) {
            if (is_string($value) && strlen($value) > 100) {
                echo "$key: " . substr($value, 0, 100) . "...\n";
            } else {
                echo "$key: " . (is_null($value) ? 'NULL' : $value) . "\n";
            }
        }
    } else {
        echo "API вернул пустой результат\n";
    }
} catch (Exception $e) {
    echo "Ошибка API: " . $e->getMessage() . "\n";
}
