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

            // Собрать уникальные сезоны с информацией о соревновании
            $seasons = collect();
            foreach ($events as $event) {
                if ($event->competition) {
                    // Получаем все сезоны соревнования с информацией о соревновании
                    $eventSeasons = $event->competition->seasons()
                        ->with('competition:id,title,title_short')
                        ->get();
                    $seasons = $seasons->merge($eventSeasons);
                }
            }

            // Убрать дубликаты и отсортировать
            $uniqueSeasons = $seasons->unique('id')->sortByDesc('date_from')->values();

            // Формируем названия сезонов в формате "Название турнира - Название сезона"
            $formattedSeasons = $uniqueSeasons->map(function($season) {
                $competitionTitle = $season->competition->title ?? $season->competition->title_short ?? 'Неизвестный турнир';
                $seasonName = $season->title ?? 'Без названия';
                $season->display_name = $competitionTitle . ' - ' . $seasonName;
                return $season;
            });

            // Если нет сезонов, создаем виртуальный сезон "Все время"
            if ($formattedSeasons->isEmpty()) {
                $virtualSeason = (object) [
                    'id' => 'all',
                    'title' => 'Все время',
                    'display_name' => 'Все время',
                    'date_from' => null,
                    'date_to' => null,
                    'competition_id' => null,
                    'is_virtual' => true
                ];
                $formattedSeasons = collect([$virtualSeason]);
            }

            return response()->json([
                'success' => true,
                'data' => $formattedSeasons,
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
    public function getPlayerStatsBySeason(Club $club, $seasonId): JsonResponse
    {
        try {
            // Если это виртуальный сезон "Все время"
            if ($seasonId === 'all') {
                return $this->getPlayerStatsOverall($club);
            }

            // Получаем сезон
            $season = CompetitionSeason::find($seasonId);
            if (!$season) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не найден'
                ], 404);
            }

            // Получить события клуба для данного соревнования (без ограничения по датам сезона)
            $events = Event::where(function($query) use ($club) {
                    $query->where('club1_id', $club->id)
                          ->orWhere('club2_id', $club->id);
                })
                ->where('competition_id', $season->competition_id)
                ->with([
                    'actions' => function($query) use ($club) {
                        $query->where('club_id', $club->id)
                              ->with(['person.activeAmpluaMemberships.amplua', 'person.mainImage', 'actionType']);
                    },
                    'lineups' => function($query) use ($club) {
                        $query->where('club_id', $club->id)
                              ->with(['person.activeAmpluaMemberships.amplua', 'person.mainImage']);
                    }
                ])
                ->get();

                        // Собрать статистику игроков
            $playerStats = [];
            $playerMatches = [];

            // Сначала подсчитаем матчи для каждого игрока из состава
            foreach ($events as $event) {
                foreach ($event->lineups as $lineup) {
                    $playerId = $lineup->person_id;
                    if (!isset($playerMatches[$playerId])) {
                        $playerMatches[$playerId] = [];
                    }
                    if (!in_array($event->id, $playerMatches[$playerId])) {
                        $playerMatches[$playerId][] = $event->id;
                    }
                }
            }

            // Теперь подсчитаем действия и создадим статистику
            foreach ($events as $event) {
                foreach ($event->actions as $action) {
                    $playerId = $action->person_id;

                    if (!isset($playerStats[$playerId])) {
                        $playerStats[$playerId] = [
                            'player' => [
                                'id' => $action->person->id,
                                'full_name' => $action->person->full_name,
                                'player_number' => $action->person->player_number,
                                'amplua' => $action->person->activeAmpluaMemberships->first()?->amplua?->name ?? 'Не указано',
                                'main_image' => $action->person->mainImage
                            ],
                            'actions' => [],
                            'total_matches' => 0
                        ];
                    }

                    $actionType = $action->actionType->name;
                    if (!isset($playerStats[$playerId]['actions'][$actionType])) {
                        $playerStats[$playerId]['actions'][$actionType] = 0;
                    }

                    // Для типов действий с group=2 суммируем значение поля value
                    // Для остальных считаем количество событий
                    if ($action->actionType->group == 2) {
                        $playerStats[$playerId]['actions'][$actionType] += $action->value ?? 0;
                    } else {
                        $playerStats[$playerId]['actions'][$actionType]++;
                    }
                }
            }

            // Добавить количество матчей из состава
            foreach ($playerStats as $playerId => &$stats) {
                $stats['total_matches'] = count($playerMatches[$playerId] ?? []);
            }

            // Преобразовать в массив и отсортировать по количеству матчей
            $result = array_values($playerStats);
            usort($result, function($a, $b) {
                return $b['total_matches'] - $a['total_matches'];
            });

            // Собрать информацию о типах действий
            $actionTypesInfo = [];
            foreach ($result as $player) {
                foreach ($player['actions'] as $actionName => $count) {
                    if (!isset($actionTypesInfo[$actionName])) {
                        // Найти тип действия в базе
                        $actionType = \App\Models\ActionType::where('name', $actionName)->first();
                        if ($actionType) {
                            $actionTypesInfo[$actionName] = [
                                'short_name' => $actionType->short_name ?: $actionType->name,
                                'icon' => $actionType->icon,
                                'color' => $actionType->color,
                                'full_name' => $actionType->name
                            ];
                        } else {
                            $actionTypesInfo[$actionName] = [
                                'short_name' => $actionName,
                                'icon' => null,
                                'color' => null,
                                'full_name' => $actionName
                            ];
                        }
                    }
                }
            }

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
                    'action_types' => $actionTypesInfo,
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
            // Получить все события клуба с действиями и составом
            $events = Event::where('club1_id', $club->id)
                ->orWhere('club2_id', $club->id)
                ->with([
                    'actions' => function($query) use ($club) {
                        $query->where('club_id', $club->id)
                              ->with(['person.activeAmpluaMemberships.amplua', 'person.mainImage', 'actionType']);
                    },
                    'lineups' => function($query) use ($club) {
                        $query->where('club_id', $club->id)
                              ->with(['person.activeAmpluaMemberships.amplua', 'person.mainImage']);
                    },
                    'competition.seasons'
                ])
                ->get();

            $playerStats = [];
            $playerMatches = [];
            $playerSeasons = [];

                        // Сначала подсчитаем матчи для каждого игрока из состава
            foreach ($events as $event) {
                foreach ($event->lineups as $lineup) {
                    $playerId = $lineup->person_id;
                    if (!isset($playerMatches[$playerId])) {
                        $playerMatches[$playerId] = [];
                    }
                    if (!in_array($event->id, $playerMatches[$playerId])) {
                        $playerMatches[$playerId][] = $event->id;
                    }
                }
            }

            // Теперь подсчитаем действия и создадим статистику
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
                                'amplua' => $action->person->activeAmpluaMemberships->first()?->amplua?->name ?? 'Не указано',
                                'main_image' => $action->person->mainImage
                            ],
                            'actions' => [],
                            'total_matches' => 0,
                            'total_seasons' => 0
                        ];
                    }

                                        $actionType = $action->actionType->name;

                    // Для всех типов действий сначала добавляем в общее поле
                    if (!isset($playerStats[$playerId]['actions'][$actionType])) {
                        $playerStats[$playerId]['actions'][$actionType] = 0;
                    }

                    // Для типов действий с group=2 суммируем значение поля value
                    // Для остальных считаем количество событий
                    if ($action->actionType->group == 2) {
                        $playerStats[$playerId]['actions'][$actionType] += $action->value ?? 0;
                    } else {
                        $playerStats[$playerId]['actions'][$actionType]++;
                    }

                    // Дополнительно группируем головы (group=1) в общее поле "ГОЛЫ"
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats[$playerId]['actions']['ГОЛЫ'])) {
                            $playerStats[$playerId]['actions']['ГОЛЫ'] = 0;
                        }
                        $playerStats[$playerId]['actions']['ГОЛЫ'] += $action->value ?? 1;
                    }
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

            // Собрать информацию о типах действий
            $actionTypesInfo = [];
            foreach ($result as $player) {
                foreach ($player['actions'] as $actionName => $count) {
                    if (!isset($actionTypesInfo[$actionName])) {
                        // Специальная обработка для поля "ГОЛЫ"
                        if ($actionName === 'ГОЛЫ') {
                            $actionTypesInfo[$actionName] = [
                                'short_name' => 'ГОЛЫ',
                                'short_name_table' => 'ГОЛЫ',
                                'icon' => 'heroicons:fire',
                                'color' => 'text-red-500',
                                'full_name' => 'Голы (сумма всех типов голов)'
                            ];
                        } else {
                            // Найти тип действия в базе
                            $actionType = \App\Models\ActionType::where('name', $actionName)->first();
                            if ($actionType) {
                                $actionTypesInfo[$actionName] = [
                                    'short_name' => $actionType->short_name ?: $actionType->name,
                                    'short_name_table' => $actionType->short_name_table ?: $actionType->short_name ?: $actionType->name,
                                    'icon' => $actionType->icon,
                                    'color' => $actionType->color,
                                    'full_name' => $actionType->name
                                ];
                            } else {
                                $actionTypesInfo[$actionName] = [
                                    'short_name' => $actionName,
                                    'short_name_table' => $actionName,
                                    'icon' => null,
                                    'color' => null,
                                    'full_name' => $actionName
                                ];
                            }
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'players' => $result,
                    'action_types' => $actionTypesInfo,
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
