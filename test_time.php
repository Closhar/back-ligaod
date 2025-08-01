<?php

require_once 'vendor/autoload.php';

use Carbon\Carbon;

echo "=== Тест времени на сервере ===\n";
echo "Текущее время сервера: " . now() . "\n";
echo "Текущее время UTC: " . Carbon::now('UTC') . "\n";
echo "Текущее время Moscow: " . Carbon::now('Europe/Moscow') . "\n";
echo "Сегодняшняя дата (now): " . now()->toDateString() . "\n";
echo "Сегодняшняя дата (UTC): " . Carbon::now('UTC')->toDateString() . "\n";
echo "Сегодняшняя дата (Moscow): " . Carbon::now('Europe/Moscow')->toDateString() . "\n";
echo "Часовой пояс сервера: " . date_default_timezone_get() . "\n";
echo "================================\n";
