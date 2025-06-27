<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MenuSection;

echo "MenuSection count: " . MenuSection::count() . "\n";
echo "Active MenuSection count: " . MenuSection::active()->count() . "\n";

$sections = MenuSection::active()->ordered()->select('id', 'name')->get();
echo "Active sections:\n";
foreach ($sections as $section) {
    echo $section->id . ': ' . $section->name . "\n";
}

echo "\nAll sections:\n";
$allSections = MenuSection::select('id', 'name', 'status')->get();
foreach ($allSections as $section) {
    echo $section->id . ': ' . $section->name . ' (status: ' . ($section->status ? 'active' : 'inactive') . ")\n";
}
