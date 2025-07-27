<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверка изображений клубов...\n\n";

try {
    echo "=== ВСЕ КЛУБЫ ===\n";
    $clubs = DB::table('clubs')->get();
    echo "Всего клубов: " . $clubs->count() . "\n\n";

    foreach ($clubs as $club) {
        echo "ID: {$club->id}, Название: {$club->title}\n";
        echo "  Изображение: " . ($club->image ?: 'НЕТ') . "\n";
        echo "  Путь к изображению: " . ($club->image_path ?: 'НЕТ') . "\n";
        echo "\n";
    }

    echo "=== КОНКРЕТНЫЕ КЛУБЫ ИГРОКА ===\n";
    $playerClubs = DB::table('clubs')->whereIn('id', [292, 296])->get();

    foreach ($playerClubs as $club) {
        echo "Клуб: {$club->title} (ID: {$club->id})\n";
        echo "  Изображение: " . ($club->image ?: 'НЕТ') . "\n";
        echo "  Путь к изображению: " . ($club->image_path ?: 'НЕТ') . "\n";

        // Проверяем, есть ли файл изображения
        if ($club->image) {
            $imagePath = 'storage/' . $club->image;
            if (file_exists($imagePath)) {
                echo "  ✓ Файл изображения существует\n";
            } else {
                echo "  ❌ Файл изображения не найден: {$imagePath}\n";
            }
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
