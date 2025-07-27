<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверка таблицы competition_season...\n\n";

try {
    echo "=== ВСЕ ЗАПИСИ В TABLЕ competition_season ===\n";
    $allRecords = DB::table('competition_season')->get();
    echo "Всего записей: " . $allRecords->count() . "\n\n";

    foreach ($allRecords as $record) {
        echo "ID: {$record->id}, Competition ID: {$record->competition_id}, Season ID: {$record->season_id}\n";
    }

    echo "\n=== ПРОВЕРКА КОНКРЕТНЫХ СОРЕВНОВАНИЙ ===\n";
    $competition111 = DB::table('competition_season')->where('competition_id', 111)->get();
    echo "Записей для соревнования 111: " . $competition111->count() . "\n";

    $competition95 = DB::table('competition_season')->where('competition_id', 95)->get();
    echo "Записей для соревнования 95: " . $competition95->count() . "\n";

    echo "\n=== ПРОВЕРКА СЕЗОНОВ ===\n";
    $seasons = DB::table('seasons')->get();
    echo "Всего сезонов в таблице: " . $seasons->count() . "\n";
    foreach ($seasons as $season) {
        echo "- ID: {$season->id}, Title: {$season->title}\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
