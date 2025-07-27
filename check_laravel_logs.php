<?php

require_once 'vendor/autoload.php';

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверка логов Laravel...\n\n";

try {
    $logFile = 'storage/logs/laravel.log';

    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $lines = explode("\n", $logs);

        // Показываем последние 100 строк
        $recentLines = array_slice($lines, -100);

        echo "=== ПОСЛЕДНИЕ 100 СТРОК ЛОГОВ ===\n";
        foreach ($recentLines as $line) {
            if (trim($line) !== '') {
                echo $line . "\n";
            }
        }
    } else {
        echo "Файл логов не найден: {$logFile}\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
