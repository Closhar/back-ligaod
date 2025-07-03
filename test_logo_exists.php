<?php

// Проверка существования файла логотипа
$logoPath = __DIR__ . '/public/storage/logos/default-logo.png';
echo "Checking logo file: $logoPath\n";
echo "File exists: " . (file_exists($logoPath) ? 'YES' : 'NO') . "\n";

// Проверка параметра в базе данных
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Param;

$param = Param::where('name', 'person_logo')->first();
if ($param) {
    echo "Found person_logo param: " . $param->value . "\n";
    $fullPath = __DIR__ . '/public/storage/' . $param->value;
    echo "Full path: $fullPath\n";
    echo "File exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
} else {
    echo "person_logo param not found in database\n";
}
