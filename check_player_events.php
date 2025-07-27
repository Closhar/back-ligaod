<?php

require_once 'vendor/autoload.php';

use App\Models\Event;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверка событий игрока и их привязки к сезонам...\n\n";

try {
    $personId = 40;

    echo "=== СОБЫТИЯ ИГРОКА {$personId} ===\n";

    // Получаем все события игрока
    $events = Event::where(function($query) use ($personId) {
        $query->whereHas('actions', function($subQuery) use ($personId) {
            $subQuery->where('person_id', $personId);
        })
        ->orWhereHas('lineups', function($subQuery) use ($personId) {
            $subQuery->where('person_id', $personId);
        });
    })
    ->with(['competition', 'actions' => function($query) use ($personId) {
        $query->where('person_id', $personId)->with(['actionType', 'club.city']);
    }, 'lineups' => function($query) use ($personId) {
        $query->where('person_id', $personId);
    }])
    ->get();

    echo "Всего событий: " . $events->count() . "\n\n";

    foreach ($events as $event) {
        echo "--- Событие ID: {$event->id} ---\n";
        echo "Соревнование: {$event->competition->title} (ID: {$event->competition->id})\n";
        echo "Дата: {$event->date_from}\n";

        // Проверяем привязку к сезонам
        $competitionSeasons = DB::table('competition_seasons')
            ->where('competition_id', $event->competition->id)
            ->get();

        echo "Привязки к сезонам: " . $competitionSeasons->count() . "\n";
        foreach ($competitionSeasons as $cs) {
            $season = DB::table('seasons')->where('id', $cs->season_id)->first();
            if ($season) {
                echo "  - Сезон: {$season->title} (ID: {$season->id})\n";
            }
        }

        // Действия игрока
        echo "Действия игрока:\n";
        foreach ($event->actions as $action) {
            $actionType = $action->actionType;
            if (!$actionType) continue;

            $clubInfo = $action->club ? " ({$action->club->title})" : " (без клуба)";
            echo "  - {$actionType->name} (группа: {$actionType->group}){$clubInfo}\n";
        }

        // Состав
        $lineupCount = $event->lineups->count();
        if ($lineupCount > 0) {
            echo "  - В составе: да\n";
        }

        echo "\n";
    }

    echo "=== ВСЕ СЕЗОНЫ ===\n";
    $seasons = DB::table('seasons')->get();
    foreach ($seasons as $season) {
        echo "ID: {$season->id}, Title: {$season->title}\n";
    }

    echo "\n=== ВСЕ СОРЕВНОВАНИЯ С ПРИВЯЗКАМИ ===\n";
    $competitions = DB::table('competitions')->get();
    foreach ($competitions as $comp) {
        $seasons = DB::table('competition_seasons')
            ->where('competition_id', $comp->id)
            ->join('seasons', 'competition_seasons.season_id', '=', 'seasons.id')
            ->select('seasons.title')
            ->get();

        $seasonTitles = $seasons->pluck('title')->implode(', ');
        echo "ID: {$comp->id}, Title: {$comp->title}, Сезоны: {$seasonTitles}\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
