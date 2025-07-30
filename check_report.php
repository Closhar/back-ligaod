<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Event;

echo "Проверка поля report в таблице events:\n";
echo "=====================================\n\n";

$event = Event::find(1);
if ($event) {
    echo "Event ID: " . $event->id . "\n";
    echo "Event title: " . $event->title . "\n";
    echo "Event about: " . ($event->about ?? 'NULL') . "\n";
    echo "Event report: " . ($event->report ?? 'NULL') . "\n";
    echo "Report length: " . (strlen($event->report ?? '') ?: 0) . "\n";
    echo "Report is null: " . (is_null($event->report) ? 'YES' : 'NO') . "\n";
    echo "Report is empty: " . (empty($event->report) ? 'YES' : 'NO') . "\n";
} else {
    echo "Event with ID 1 not found!\n";
}

echo "\nПроверка всех событий с полем report:\n";
echo "=====================================\n";

$eventsWithReport = Event::whereNotNull('report')->where('report', '!=', '')->get();
echo "Events with non-empty report: " . $eventsWithReport->count() . "\n";

foreach ($eventsWithReport as $event) {
    echo "Event ID: " . $event->id . ", Report: " . substr($event->report, 0, 50) . "...\n";
}
