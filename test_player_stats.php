<?php
require_once 'vendor/autoload.php';

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Event;
use App\Models\Person;

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Тестирование данных игрока\n";
echo "==========================\n\n";

// Найдем первого игрока
$person = Person::first();
if (!$person) {
    echo "❌ Игроки не найдены\n";
    exit;
}

echo "👤 Игрок: {$person->full_name} (ID: {$person->id})\n\n";

// Найдем события с участием этого игрока
$events = Event::whereHas('actions', function($query) use ($person) {
        $query->where('person_id', $person->id);
    })
    ->orWhereHas('lineups', function($query) use ($person) {
        $query->where('person_id', $person->id);
    })
    ->with(['competition', 'actions.actionType', 'lineups'])
    ->get();

echo "📊 Событий с участием игрока: " . $events->count() . "\n\n";

if ($events->count() > 0) {
    // Группируем по соревнованиям
    $competitions = $events->groupBy('competition_id');

    echo "🏆 Соревнования:\n";
    foreach ($competitions as $competitionId => $competitionEvents) {
        $competition = Competition::find($competitionId);
        if ($competition) {
            echo "  - {$competition->title} (ID: {$competition->id})\n";
            echo "    Событий: " . $competitionEvents->count() . "\n";

            // Найдем сезоны для этого соревнования
            $seasons = CompetitionSeason::where('competition_id', $competitionId)->get();
            if ($seasons->count() > 0) {
                echo "    Сезоны:\n";
                foreach ($seasons as $season) {
                    echo "      * {$season->name} (ID: {$season->id})\n";
                }
            }
            echo "\n";
        }
    }

    // Покажем несколько событий
    echo "📅 Последние события:\n";
    foreach ($events->take(3) as $event) {
        echo "  - {$event->name} (ID: {$event->id})\n";
        echo "    Соревнование: " . ($event->competition->title ?? 'Нет') . "\n";
        echo "    Действий: " . $event->actions->count() . "\n";
        echo "    В составах: " . $event->lineups->count() . "\n\n";
    }
} else {
    echo "❌ Событий с участием игрока не найдено\n";
}

echo "Тестирование завершено.\n";
?>
