<?php

require_once 'vendor/autoload.php';

use App\Models\EventAction;
use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверка значений действий игрока...\n\n";

try {
    $personId = 40;

    echo "=== ДЕЙСТВИЯ ИГРОКА {$personId} ===\n";

    // Получаем все действия игрока
    $actions = EventAction::where('person_id', $personId)
        ->with(['actionType', 'club.city', 'event.competition'])
        ->get();

    echo "Всего действий: " . $actions->count() . "\n\n";

    foreach ($actions as $action) {
        $actionType = $action->actionType;
        if (!$actionType) continue;

        $clubInfo = $action->club ? " ({$action->club->title})" : " (без клуба)";
        $eventInfo = $action->event ? " [Событие: {$action->event->competition->title}]" : "";

        echo "--- Действие ID: {$action->id} ---\n";
        echo "Тип: {$actionType->name} (группа: {$actionType->group})\n";
        echo "Значение: {$action->value}\n";
        echo "Клуб: {$clubInfo}\n";
        echo "Событие: {$eventInfo}\n";
        echo "\n";
    }

        echo "=== ПРОВЕРКА СОСТАВОВ ===\n";
    $lineups = DB::table('event_lineups')->where('person_id', $personId)->get();
    echo "Записей в составе: " . $lineups->count() . "\n";

    foreach ($lineups as $lineup) {
        echo "Event ID: {$lineup->event_id}, Person ID: {$lineup->person_id}\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
