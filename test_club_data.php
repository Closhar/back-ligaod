<?php

require_once 'vendor/autoload.php';

use App\Models\Event;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверка данных о клубах в API...\n\n";

try {
    $personId = 40;
    $seasonId = 1;

    echo "=== ПРОВЕРКА ДАННЫХ О КЛУБАХ ===\n";
    echo "Person ID: {$personId}\n";
    echo "Season ID: {$seasonId}\n\n";

    // Получаем сезон
    $season = Season::find($seasonId);
    echo "Сезон: {$season->title}\n";

    // Получаем соревнования для этого сезона
    $competitionIds = DB::table('competition_seasons')
        ->where('season_id', $season->id)
        ->pluck('competition_id');

    echo "Соревнований: " . $competitionIds->count() . "\n";

    // Получаем события игрока с данными о клубах
    $events = Event::whereIn('competition_id', $competitionIds)
        ->where(function($query) use ($personId) {
            $query->whereHas('actions', function($subQuery) use ($personId) {
                $subQuery->where('person_id', $personId);
            });
        })
        ->with(['actions' => function($query) use ($personId) {
            $query->where('person_id', $personId)
                  ->with(['actionType', 'club.city']);
        }])
        ->get();

    echo "Событий: " . $events->count() . "\n\n";

    // Проверяем каждое действие
    foreach ($events as $event) {
        echo "--- Событие ID: {$event->id} ---\n";
        echo "Соревнование: {$event->competition->title}\n";

        foreach ($event->actions as $action) {
            $actionType = $action->actionType;
            if (!$actionType) continue;

            echo "  Действие: {$actionType->name}\n";
            echo "  Значение: {$action->value}\n";

            if ($action->club) {
                echo "  Клуб: {$action->club->title}\n";
                echo "  ID клуба: {$action->club->id}\n";
                echo "  Путь к изображению: {$action->club->image_path}\n";
                if ($action->club->city) {
                    echo "  Город: {$action->club->city->title}\n";
                }
            } else {
                echo "  ❌ Клуб не найден!\n";
            }
            echo "\n";
        }
    }

    // Тестируем API напрямую
    echo "=== ТЕСТИРОВАНИЕ API ===\n";

    // Создаем HTTP запрос
    $request = \Illuminate\Http\Request::create(
        "/api/people/{$personId}/statistics/season/{$seasonId}",
        'GET'
    );

    // Получаем роутер
    $router = app('router');

    // Находим роут
    $route = $router->getRoutes()->match($request);

    if (!$route) {
        echo "❌ Роут не найден!\n";
        exit;
    }

    // Выполняем запрос
    $response = app()->handle($request);
    $content = $response->getContent();
    $data = json_decode($content, true);

    echo "Статус ответа: " . $response->getStatusCode() . "\n";

    if (isset($data['data']['statistics_by_club'])) {
        echo "\nДанные о клубах в API:\n";
        foreach ($data['data']['statistics_by_club'] as $action => $clubs) {
            echo "  {$action}:\n";
            foreach ($clubs as $clubId => $clubData) {
                echo "    Клуб ID {$clubId}: {$clubData['club']['title']}\n";
                echo "    Количество: {$clubData['count']}\n";
                echo "    Путь к изображению: {$clubData['club']['image_path']}\n";
                echo "    Город: {$clubData['club']['city']}\n";
                echo "\n";
            }
        }
    } else {
        echo "❌ Данные о клубах отсутствуют в API!\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
