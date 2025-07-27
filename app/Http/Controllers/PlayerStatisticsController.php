<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\CompetitionSeason;
use App\Models\Event;
use App\Models\EventAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerStatisticsController extends Controller
{
    /**
     * Получить все сезоны соревнований для клуба
     */
    public function getClubSeasons(Club $club): JsonResponse
    {
        try {
            // Получить все события клуба с соревнованиями и сезонами
            $events = Event::where('club1_id', $club->id)
                ->orWhere('club2_id', $club->id)
                ->with(['competition.seasons' => function($query) {
                    $query->orderBy('date_from', 'desc');
                }])
                ->get();

            // Собрать уникальные сезоны
            $seasons = collect();
            foreach ($events as $event) {
                if ($event->competition && $event->date_from) {
                    $eventSeasons = $event->competition->seasons()
                        ->where('date_from', '<=', $event->date_from)
                        ->where('date_to', '>=', $event->date_from)
                        ->get();
                    $seasons = $seasons->merge($eventSeasons);
                }
            }

            // Убрать дубликаты и отсортировать
            $uniqueSeasons = $seasons->unique('id')->sortByDesc('date_from')->values();

            return response()->json([
                'success' => true,
                'data' => $uniqueSeasons,
                'message' => 'Сезоны успешно получены'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении сезонов: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику игроков по конкретному сезону
     */
    public function getPlayerStatsBySeason(Club $club, CompetitionSeason $season): JsonResponse
    {
        try {
            // Получить события клуба в рамках сезона
            $events = Event::where(function($query) use ($club) {
                    $query->where('club1_id', $club->id)
                          ->orWhere('club2_id', $club->id);
                })
                ->where('competition_id', $season->competition_id)
                ->where('date_from', '>=', $season->date_from)
                ->where('date_from', '<=', $season->date_to)
                ->with(['actions' => function($query) use ($club) {
                    $query->where('club_id', $club->id)
                          ->with(['person.activeAmpluaMemberships.amplua', 'actionType']);
                }])
                ->get();

            // Собрать статистику игроков
            $playerStats = [];
            $playerMatches = [];

            foreach ($events as $event) {
                // Подсчитать матчи для каждого игрока
                foreach ($event->actions as $action) {
                    $playerId = $action->person_id;
                    if (!isset($playerMatches[$playerId])) {
                        $playerMatches[$playerId] = [];
                    }
                    if (!in_array($event->id, $playerMatches[$playerId])) {
                        $playerMatches[$playerId][] = $event->id;
                    }
                }

                // Подсчитать действия
                foreach ($event->actions as $action) {
                    $playerId = $action->person_id;

                    if (!isset($playerStats[$playerId])) {
                        $playerStats[$playerId] = [
                            'player' => [
                                'id' => $action->person->id,
                                'full_name' => $action->person->full_name,
                                'player_number' => $action->person->player_number,
                                'amplua' => $action->person->activeAmpluaMemberships->first()?->amplua?->name ?? 'Не указано'
                            ],
                            'actions' => [],
                            'total_matches' => 0
                        ];
                    }

                    $actionType = $action->actionType->name;
                    if (!isset($playerStats[$playerId]['actions'][$actionType])) {
                        $playerStats[$playerId]['actions'][$actionType] = 0;
                    }
                    $playerStats[$playerId]['actions'][$actionType]++;
                }
            }

            // Добавить количество матчей
            foreach ($playerStats as $playerId => &$stats) {
                $stats['total_matches'] = count($playerMatches[$playerId] ?? []);
            }

            // Преобразовать в массив и отсортировать по количеству матчей
            $result = array_values($playerStats);
            usort($result, function($a, $b) {
                return $b['total_matches'] - $a['total_matches'];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'season' => [
                        'id' => $season->id,
                        'name' => $season->name,
                        'date_from' => $season->date_from,
                        'date_to' => $season->date_to,
                        'competition' => [
                            'id' => $season->competition->id,
                            'title' => $season->competition->title
                        ]
                    ],
                    'players' => $result,
                    'total_events' => $events->count()
                ],
                'message' => 'Статистика игроков успешно получена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить общую статистику игроков по всем сезонам
     */
    public function getPlayerStatsOverall(Club $club): JsonResponse
    {
        try {
            // Получить все события клуба с действиями
            $events = Event::where('club1_id', $club->id)
                ->orWhere('club2_id', $club->id)
                ->with(['actions' => function($query) use ($club) {
                    $query->where('club_id', $club->id)
                          ->with(['person.activeAmpluaMemberships.amplua', 'actionType']);
                }, 'competition.seasons'])
                ->get();

            $playerStats = [];
            $playerMatches = [];
            $playerSeasons = [];

            foreach ($events as $event) {
                // Определить сезон для события
                $eventSeason = null;
                if ($event->competition && $event->date_from) {
                    $eventSeason = $event->competition->seasons()
                        ->where('date_from', '<=', $event->date_from)
                        ->where('date_to', '>=', $event->date_from)
                        ->first();
                }

                foreach ($event->actions as $action) {
                    $playerId = $action->person_id;

                    // Подсчитать матчи
                    if (!isset($playerMatches[$playerId])) {
                        $playerMatches[$playerId] = [];
                    }
                    if (!in_array($event->id, $playerMatches[$playerId])) {
                        $playerMatches[$playerId][] = $event->id;
                    }

                    // Подсчитать сезоны
                    if ($eventSeason) {
                        if (!isset($playerSeasons[$playerId])) {
                            $playerSeasons[$playerId] = [];
                        }
                        if (!in_array($eventSeason->id, $playerSeasons[$playerId])) {
                            $playerSeasons[$playerId][] = $eventSeason->id;
                        }
                    }

                    // Подсчитать действия
                    if (!isset($playerStats[$playerId])) {
                        $playerStats[$playerId] = [
                            'player' => [
                                'id' => $action->person->id,
                                'full_name' => $action->person->full_name,
                                'player_number' => $action->person->player_number,
                                'amplua' => $action->person->activeAmpluaMemberships->first()?->amplua?->name ?? 'Не указано'
                            ],
                            'actions' => [],
                            'total_matches' => 0,
                            'total_seasons' => 0
                        ];
                    }

                    $actionType = $action->actionType->name;
                    if (!isset($playerStats[$playerId]['actions'][$actionType])) {
                        $playerStats[$playerId]['actions'][$actionType] = 0;
                    }
                    $playerStats[$playerId]['actions'][$actionType]++;
                }
            }

            // Добавить итоговые данные
            foreach ($playerStats as $playerId => &$stats) {
                $stats['total_matches'] = count($playerMatches[$playerId] ?? []);
                $stats['total_seasons'] = count($playerSeasons[$playerId] ?? []);
            }

            // Преобразовать в массив и отсортировать
            $result = array_values($playerStats);
            usort($result, function($a, $b) {
                return $b['total_matches'] - $a['total_matches'];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'players' => $result,
                    'total_events' => $events->count()
                ],
                'message' => 'Общая статистика игроков успешно получена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении общей статистики: ' . $e->getMessage()
            ], 500);
        }
    }
}
