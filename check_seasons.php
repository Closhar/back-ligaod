<?php

require_once 'vendor/autoload.php';

use App\Models\CompetitionSeason;
use App\Models\Event;
use App\Models\Person;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверка состояния данных сезонов...\n\n";

try {
    // Проверяем таблицу seasons
    echo "=== ТАБЛИЦА SEASONS ===\n";
    $seasons = Season::all();
    echo "Всего сезонов: " . $seasons->count() . "\n";
    foreach ($seasons as $season) {
        echo "- ID: {$season->id}, Title: {$season->title}\n";
    }
    echo "\n";

    // Проверяем таблицу competition_seasons
    echo "=== ТАБЛИЦА COMPETITION_SEASONS ===\n";
    $competitionSeasons = CompetitionSeason::all();
    echo "Всего записей: " . $competitionSeasons->count() . "\n";

    $withSeasonId = $competitionSeasons->whereNotNull('season_id')->count();
    $withoutSeasonId = $competitionSeasons->whereNull('season_id')->count();

    echo "С season_id: {$withSeasonId}\n";
    echo "Без season_id: {$withoutSeasonId}\n";

    if ($withoutSeasonId > 0) {
        echo "\nЗаписи без season_id:\n";
        foreach ($competitionSeasons->whereNull('season_id') as $cs) {
            echo "- ID: {$cs->id}, Competition: {$cs->competition_id}, Title: {$cs->title}\n";
        }
    }
    echo "\n";

    // Проверяем события
    echo "=== СОБЫТИЯ ===\n";
    $events = Event::count();
    echo "Всего событий: {$events}\n";

    $eventsWithCompetition = Event::whereNotNull('competition_id')->count();
    echo "Событий с competition_id: {$eventsWithCompetition}\n";
    echo "\n";

    // Проверяем конкретного игрока (если указан ID)
    if (isset($argv[1])) {
        $personId = $argv[1];
        echo "=== ПРОВЕРКА ИГРОКА {$personId} ===\n";

        $person = Person::find($personId);
        if ($person) {
            echo "Игрок найден: {$person->full_name}\n";

            // События игрока
            $playerEvents = Event::where(function($query) use ($personId) {
                $query->whereHas('actions', function($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                })
                ->orWhereHas('lineups', function($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                });
            })->get();

            echo "Событий игрока: " . $playerEvents->count() . "\n";

            $playerCompetitions = collect();
            foreach ($playerEvents as $event) {
                echo "- Event ID: {$event->id}, Competition: {$event->competition_id}\n";
                $playerCompetitions->put($event->competition_id, $event->competition_id);
            }

            echo "\nУникальных соревнований игрока: " . $playerCompetitions->count() . "\n";

            // Проверяем связи соревнований с сезонами
            echo "\n=== СВЯЗИ СОРЕВНОВАНИЙ С СЕЗОНАМИ ===\n";
            foreach ($playerCompetitions as $competitionId) {
                echo "Соревнование ID: {$competitionId}\n";

                $competitionSeasons = CompetitionSeason::where('competition_id', $competitionId)->get();
                echo "  Записей в competition_seasons: " . $competitionSeasons->count() . "\n";

                foreach ($competitionSeasons as $cs) {
                    $season = Season::find($cs->season_id);
                    echo "  - CompetitionSeason ID: {$cs->id}, Season ID: {$cs->season_id}, Season Title: " . ($season ? $season->title : 'НЕ НАЙДЕН') . "\n";
                }
                echo "\n";
            }
        } else {
            echo "Игрок с ID {$personId} не найден\n";
        }
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
