<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MenuSection;

$section = MenuSection::find(1);
if ($section) {
    $section->update(['status' => true]);
    echo "Раздел меню '{$section->name}' активирован\n";
} else {
    echo "Раздел меню не найден\n";
}
