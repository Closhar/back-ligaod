<?php

require_once 'vendor/autoload.php';

use App\Models\Competition;
use App\Models\Event;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Пошаговая отладка поиска сезонов для игрока...\n\n";

try {
    $personId = 40;

    echo "=== ШАГ 1: Поиск событий игрока ===\n";
    $events = Event::where(function($query) use ($personId) {
        $query->whereHas('actions', function($subQuery) use ($personId) {
            $subQuery->where('person_id', $personId);
        })
        ->orWhereHas('lineups', function($subQuery) use ($personId) {
            $subQuery->where('person_id', $personId);
        });
    })->get();

    echo "Найдено событий: " . $events->count() . "\n";
    foreach ($events as $event) {
        echo "- Event ID: {$event->id}, Competition: {$event->competition_id}\n";
    }

    echo "\n=== ШАГ 2: Сбор уникальных соревнований ===\n";
    $playerCompetitions = collect();
    foreach ($events as $event) {
        if ($event->competition_id) {
            $playerCompetitions->put($event->competition_id, $event->competition_id);
        }
    }
    echo "Уникальных соревнований: " . $playerCompetitions->count() . "\n";
    foreach ($playerCompetitions as $compId) {
        echo "- Competition ID: {$compId}\n";
    }

    echo "\n=== ШАГ 3: Поиск сезонов для каждого соревнования ===\n";
    $playerSeasons = collect();

    foreach ($playerCompetitions as $competitionId) {
        echo "Обрабатываем соревнование: {$competitionId}\n";

        // Проверяем записи в competition_season
        $competitionSeasons = DB::table('competition_season')
            ->where('competition_id', $competitionId)
            ->get();

        echo "  Записей в competition_season: " . $competitionSeasons->count() . "\n";

        foreach ($competitionSeasons as $cs) {
            echo "  - CompetitionSeason ID: {$cs->id}, season_id: {$cs->season_id}\n";

            if ($cs->season_id) {
                $season = Season::find($cs->season_id);
                if ($season) {
                    $playerSeasons->put($season->id, $season);
                    echo "    ✓ Найден сезон: {$season->title} (ID: {$season->id})\n";
                } else {
                    echo "    ✗ Сезон с ID {$cs->season_id} не найден!\n";
                }
            } else {
                echo "    ⚠ season_id пустой\n";
            }
        }
    }

    echo "\n=== РЕЗУЛЬТАТ ===\n";
    echo "Итоговое количество сезонов: " . $playerSeasons->count() . "\n";
    foreach ($playerSeasons as $season) {
        echo "- Сезон: {$season->title} (ID: {$season->id})\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
