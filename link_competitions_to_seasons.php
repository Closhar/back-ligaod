<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Привязка соревнований к сезонам...\n\n";

try {
    // Получаем все соревнования
    $competitions = DB::table('competitions')->get();
    echo "Найдено соревнований: " . $competitions->count() . "\n";

    // Получаем сезоны
    $seasons = DB::table('seasons')->get();
    echo "Найдено сезонов: " . $seasons->count() . "\n";

    echo "\n=== ПРИВЯЗКА СОРЕВНОВАНИЙ К СЕЗОНАМ ===\n";

    $linkedCount = 0;

    foreach ($competitions as $competition) {
        echo "Обрабатываем соревнование: {$competition->title} (ID: {$competition->id})\n";

        // Определяем сезон на основе дат соревнования
        $seasonId = null;

        if ($competition->date_from) {
            $year = date('Y', strtotime($competition->date_from));

            // Ищем подходящий сезон
            foreach ($seasons as $season) {
                if (strpos($season->title, $year) !== false) {
                    $seasonId = $season->id;
                    echo "  → Привязываем к сезону: {$season->title} (ID: {$season->id})\n";
                    break;
                }
            }
        }

        if (!$seasonId) {
            // Если не нашли по дате, привязываем к сезону 2025/2026 (ID: 2)
            $seasonId = 2;
            echo "  → Привязываем к сезону по умолчанию: 2025/2026 (ID: 2)\n";
        }

                // Проверяем, есть ли уже такая связь
        $existing = DB::table('competition_seasons')
            ->where('competition_id', $competition->id)
            ->where('season_id', $seasonId)
            ->first();

        if (!$existing) {
            // Добавляем связь
            DB::table('competition_seasons')->insert([
                'competition_id' => $competition->id,
                'season_id' => $seasonId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "  ✓ Связь добавлена\n";
            $linkedCount++;
        } else {
            echo "  ⚠ Связь уже существует\n";
        }

        echo "\n";
    }

    echo "=== РЕЗУЛЬТАТ ===\n";
    echo "Добавлено связей: {$linkedCount}\n";

    // Проверяем результат
    $totalLinks = DB::table('competition_seasons')->count();
    echo "Всего связей в таблице: {$totalLinks}\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
