<?php

require_once 'vendor/autoload.php';

use App\Models\Event;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Отладка логики подсчета статистики по сезону...\n\n";

try {
    $personId = 40;
    $seasonId = 1; // 2025

    echo "=== ОТЛАДКА СТАТИСТИКИ ПО СЕЗОНУ ===\n";
    echo "Person ID: {$personId}\n";
    echo "Season ID: {$seasonId}\n\n";

    // Получаем сезон
    $season = Season::find($seasonId);
    if (!$season) {
        echo "❌ Сезон не найден!\n";
        exit;
    }
    echo "✓ Сезон: {$season->title}\n";

    // Получаем соревнования для этого сезона
    $competitionIds = DB::table('competition_seasons')
        ->where('season_id', $season->id)
        ->pluck('competition_id');

    echo "✓ Соревнований для сезона: " . $competitionIds->count() . "\n";
    foreach ($competitionIds as $compId) {
        echo "  - Competition ID: {$compId}\n";
    }

    // Получаем события игрока в этих соревнованиях
    $events = Event::whereIn('competition_id', $competitionIds)
        ->where(function($query) use ($personId) {
            $query->whereHas('actions', function($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                })
                ->orWhereHas('lineups', function($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                });
        })
        ->with([
            'actions' => function($query) use ($personId) {
                $query->where('person_id', $personId)
                      ->with(['actionType', 'club.city']);
            },
            'lineups' => function($query) use ($personId) {
                $query->where('person_id', $personId);
            },
            'competition'
        ])
        ->get();

    echo "\n✓ Событий для игрока: " . $events->count() . "\n";

    // Анализируем каждое событие
    $playerStats = [];
    $playerStatsByClub = [];
    $totalMatches = 0;

    foreach ($events as $event) {
        echo "\n--- Событие ID: {$event->id} ---\n";
        echo "Соревнование: {$event->competition->title}\n";

        // Подсчитываем матчи
        $lineupCount = $event->lineups->where('person_id', $personId)->count();
        if ($lineupCount > 0) {
            $totalMatches++;
            echo "✓ Игрок в составе (матч +1)\n";
        }

        // Анализируем действия
        foreach ($event->actions as $action) {
            if ($action->person_id != $personId) continue;

            $actionType = $action->actionType;
            if (!$actionType) continue;

            $actionName = $actionType->name;
            if ($actionName === 'ГОЛЫ') {
                $actionName = 'Голы всего';
            }

            echo "  Действие: {$actionName} (группа: {$actionType->group}, значение: {$action->value})\n";

            // Инициализируем статистику
            if (!isset($playerStats[$actionName])) {
                $playerStats[$actionName] = 0;
            }

            // Подсчитываем статистику
            if ($actionType->group == 2) {
                $oldValue = $playerStats[$actionName];
                $value = $action->value ?? 0;
                $playerStats[$actionName] += $value;
                echo "    Группа 2: {$oldValue} + {$value} = {$playerStats[$actionName]} (сумма)\n";
            } else {
                $oldValue = $playerStats[$actionName];
                $playerStats[$actionName]++;
                echo "    Группа не 2: {$oldValue} + 1 = {$playerStats[$actionName]} (счетчик)\n";
            }

            // Статистика по клубам
            if ($action->club) {
                $clubKey = $action->club->id;
                if (!isset($playerStatsByClub[$actionName])) {
                    $playerStatsByClub[$actionName] = [];
                }
                if (!isset($playerStatsByClub[$actionName][$clubKey])) {
                    $playerStatsByClub[$actionName][$clubKey] = [
                        'count' => 0,
                        'club' => [
                            'id' => $action->club->id,
                            'title' => $action->club->title,
                            'image_path' => $action->club->image_path,
                            'city' => $action->club->city ? $action->club->city->title : null
                        ]
                    ];
                }

                if ($actionType->group == 2) {
                    $oldClubValue = $playerStatsByClub[$actionName][$clubKey]['count'];
                    $value = $action->value ?? 0;
                    $playerStatsByClub[$actionName][$clubKey]['count'] += $value;
                    echo "    Клуб {$action->club->title}: {$oldClubValue} + {$value} = {$playerStatsByClub[$actionName][$clubKey]['count']} (сумма)\n";
                } else {
                    $oldClubValue = $playerStatsByClub[$actionName][$clubKey]['count'];
                    $playerStatsByClub[$actionName][$clubKey]['count']++;
                    echo "    Клуб {$action->club->title}: {$oldClubValue} + 1 = {$playerStatsByClub[$actionName][$clubKey]['count']} (счетчик)\n";
                }
            }
        }
    }

    echo "\n=== РЕЗУЛЬТАТ ===\n";
    echo "Всего матчей: {$totalMatches}\n";
    echo "Статистика:\n";
    foreach ($playerStats as $action => $count) {
        echo "  {$action}: {$count}\n";
    }

    echo "\nСтатистика по клубам:\n";
    foreach ($playerStatsByClub as $action => $clubs) {
        echo "  {$action}:\n";
        foreach ($clubs as $clubId => $data) {
            echo "    {$data['club']['title']}: {$data['count']}\n";
        }
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
