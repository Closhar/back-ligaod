<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\CompetitionSeason;
use App\Models\Event;
use App\Models\EventAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlayerStatisticsController extends Controller
{
    /**
     * Получить все сезоны и соревнования для клуба
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

                        Log::info('Клуб ID: ' . $club->id);
            Log::info('Найдено событий: ' . $events->count());

            // Собрать уникальные сезоны и соревнования
            $seasons = collect();
            $competitions = collect();

            foreach ($events as $event) {
                Log::info('Обрабатываем событие ID: ' . $event->id . ', competition_id: ' . $event->competition_id);

                if ($event->competition) {
                    Log::info('Добавляем соревнование ID: ' . $event->competition->id . ', title: ' . $event->competition->title);
                    // Добавляем соревнование
                    $competitions->put($event->competition->id, $event->competition);

                    // Получаем все сезоны соревнования
                    $eventSeasons = $event->competition->seasons()
                        ->with('competition:id,title,title_short')
                        ->get();
                    Log::info('Найдено сезонов для соревнования ' . $event->competition->id . ': ' . $eventSeasons->count());
                    $seasons = $seasons->merge($eventSeasons);
                } else {
                    Log::info('У события ' . $event->id . ' нет соревнования');
                }
            }

            // Убрать дубликаты сезонов и отсортировать
            $uniqueSeasons = $seasons->unique('id')->sortByDesc('date_from')->values();

            // Убрать дубликаты соревнований и отсортировать
            $uniqueCompetitions = $competitions->values()->sortBy('title');

            // Добавить информацию о сезонах для каждого соревнования
            foreach ($uniqueCompetitions as $competition) {
                $competitionSeasons = $uniqueSeasons->where('competition_id', $competition->id);
                $competition->seasons_info = $competitionSeasons->map(function($season) {
                    return [
                        'id' => $season->id,
                        'name' => $season->name,
                        'title' => $season->title,
                        'date_from' => $season->date_from,
                        'date_to' => $season->date_to
                    ];
                })->values();
            }

            // Если соревнования не найдены через события, попробуем получить их из сезонов
            if ($uniqueCompetitions->isEmpty() && $uniqueSeasons->isNotEmpty()) {
                $competitionsFromSeasons = collect();

                foreach ($uniqueSeasons as $season) {
                    if ($season->competition_id && !$season->is_virtual) {
                        $competition = \App\Models\Competition::find($season->competition_id);
                        if ($competition) {
                            // Используем union вместо put, чтобы избежать дублирования
                            $competitionsFromSeasons = $competitionsFromSeasons->union([$competition->id => $competition]);
                        }
                    }
                }

                $uniqueCompetitions = $competitionsFromSeasons->values()->sortBy('title');

                // Добавить информацию о сезонах для каждого соревнования
                foreach ($uniqueCompetitions as $competition) {
                    $competitionSeasons = $uniqueSeasons->where('competition_id', $competition->id);
                    $competition->seasons_info = $competitionSeasons->map(function($season) {
                        return [
                            'id' => $season->id,
                            'name' => $season->name,
                            'title' => $season->title,
                            'date_from' => $season->date_from,
                            'date_to' => $season->date_to
                        ];
                    })->values();
                }
            }

            Log::info('Итоговое количество сезонов: ' . $uniqueSeasons->count());
            Log::info('Итоговое количество соревнований: ' . $uniqueCompetitions->count());
            Log::info('Соревнования: ' . $uniqueCompetitions->pluck('id', 'title')->toJson());

            // Дополнительная отладка
            Log::info('Коллекция competitions до обработки: ' . $competitions->count());
            Log::info('Коллекция competitions после values(): ' . $competitions->values()->count());
            Log::info('Коллекция competitions после sortBy(): ' . $uniqueCompetitions->count());

            // Проверяем каждое соревнование
            foreach ($uniqueCompetitions as $comp) {
                Log::info('Соревнование в итоговом результате: ID=' . $comp->id . ', title=' . $comp->title);
            }

            // Если нет сезонов, создаем виртуальный сезон "Все время"
            if ($uniqueSeasons->isEmpty()) {
                $virtualSeason = (object) [
                    'id' => 'all',
                    'title' => 'Все время',
                    'display_name' => 'Все время',
                    'date_from' => null,
                    'date_to' => null,
                    'competition_id' => null,
                    'is_virtual' => true
                ];
                $uniqueSeasons = collect([$virtualSeason]);
            }

            $response = [
                'success' => true,
                'data' => [
                    'seasons' => $uniqueSeasons->values(),
                    'competitions' => $uniqueCompetitions->values()
                ],
                'message' => 'Данные успешно получены'
            ];

            Log::info('Отправляем ответ с ' . $uniqueSeasons->count() . ' сезонами и ' . $uniqueCompetitions->count() . ' соревнованиями');
            Log::info('Структура ответа: ' . json_encode($response, JSON_UNESCAPED_UNICODE));

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику игроков по конкретному соревнованию
     */
    public function getPlayerStatsByCompetition(Club $club, $competitionId): JsonResponse
    {
        try {
            // Получаем соревнование
            $competition = \App\Models\Competition::find($competitionId);
            if (!$competition) {
                return response()->json([
                    'success' => false,
                    'message' => 'Соревнование не найдено'
                ], 404);
            }

            // Получить события клуба для данного соревнования
            $events = Event::where(function($query) use ($club) {
                    $query->where('club1_id', $club->id)
                          ->orWhere('club2_id', $club->id);
                })
                ->where('competition_id', $competition->id)
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

            if ($events->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'players' => [],
                        'action_types' => [],
                        'season' => null
                    ],
                    'message' => 'Нет событий для данного соревнования'
                ]);
            }

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

                    // Дополнительно группируем головы (group=1) в общее поле "Голы всего"
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats[$playerId]['actions']['Голы всего'])) {
                            $playerStats[$playerId]['actions']['Голы всего'] = 0;
                        }
                        $playerStats[$playerId]['actions']['Голы всего'] += $action->value ?? 1;
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
                        // Специальная обработка для поля "Голы всего"
                        if ($actionName === 'Голы всего') {
                            $actionTypesInfo[$actionName] = [
                                'short_name' => 'Голы всего',
                                'short_name_table' => 'Голы всего',
                                'icon' => 'heroicons:fire',
                                'color' => 'text-red-500',
                                'full_name' => 'голы всего'
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
                    'season' => [
                        'id' => null,
                        'name' => null,
                        'date_from' => null,
                        'date_to' => null,
                        'competition' => [
                            'id' => $competition->id,
                            'title' => $competition->title
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

                    // Дополнительно группируем головы (group=1) в общее поле "Голы всего"
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats[$playerId]['actions']['Голы всего'])) {
                            $playerStats[$playerId]['actions']['Голы всего'] = 0;
                        }
                        $playerStats[$playerId]['actions']['Голы всего'] += $action->value ?? 1;
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
                        // Специальная обработка для поля "Голы всего"
                        if ($actionName === 'Голы всего') {
                            $actionTypesInfo[$actionName] = [
                                'short_name' => 'Голы всего',
                                'short_name_table' => 'Голы всего',
                                'icon' => 'heroicons:fire',
                                'color' => 'text-red-500',
                                'full_name' => 'голы всего'
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
            $playerStatsByClub = [];
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

                    // Дополнительно группируем головы (group=1) в общее поле "Голы всего"
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats[$playerId]['actions']['Голы всего'])) {
                            $playerStats[$playerId]['actions']['Голы всего'] = 0;
                        }
                        $playerStats[$playerId]['actions']['Голы всего'] += $action->value ?? 1;
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
                        // Специальная обработка для поля "Голы всего"
                        if ($actionName === 'Голы всего') {
                            $actionTypesInfo[$actionName] = [
                                'short_name' => 'Голы всего',
                                'short_name_table' => 'Голы всего',
                                'icon' => 'heroicons:fire',
                                'color' => 'text-red-500',
                                'full_name' => 'голы всего'
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

    /**
     * Получить все сезоны и соревнования для конкретного игрока
     */
    public function getPersonSeasons($personId): JsonResponse
    {
        try {
            // Получить все события игрока с соревнованиями и сезонами
            $events = Event::whereHas('actions', function($query) use ($personId) {
                    $query->where('person_id', $personId);
                })
                ->orWhereHas('lineups', function($query) use ($personId) {
                    $query->where('person_id', $personId);
                })
                ->with(['competition.seasons' => function($query) {
                    $query->orderBy('date_from', 'desc');
                }])
                ->get();

            // Собрать уникальные сезоны и соревнования
            $seasons = collect();
            $competitions = collect();

            foreach ($events as $event) {
                if ($event->competition) {
                    // Добавляем соревнование
                    $competitions->put($event->competition->id, $event->competition);

                    // Получаем все сезоны соревнования
                    $eventSeasons = $event->competition->seasons()
                        ->with('competition:id,title,title_short')
                        ->get();
                    // Используем union вместо merge, чтобы избежать дублирования
                    $seasons = $seasons->union($eventSeasons->keyBy('id'));
                }
            }

            // Убрать дубликаты сезонов и отсортировать
            $uniqueSeasons = $seasons->unique('id')->sortByDesc('date_from')->values();

            // Убрать дубликаты соревнований и отсортировать
            $uniqueCompetitions = $competitions->values()->sortBy('title');

            // Добавить информацию о сезонах для каждого соревнования
            foreach ($uniqueCompetitions as $competition) {
                $competitionSeasons = $uniqueSeasons->where('competition_id', $competition->id);
                $competition->seasons_info = $competitionSeasons->map(function($season) {
                    return [
                        'id' => $season->id,
                        'name' => $season->name,
                        'title' => $season->title,
                        'date_from' => $season->date_from,
                        'date_to' => $season->date_to
                    ];
                })->values();
            }

            // Если соревнования не найдены через события, попробуем получить их из сезонов
            if ($uniqueCompetitions->isEmpty() && $uniqueSeasons->isNotEmpty()) {
                $competitionsFromSeasons = collect();

                foreach ($uniqueSeasons as $season) {
                    if ($season->competition_id && !$season->is_virtual) {
                        $competition = \App\Models\Competition::find($season->competition_id);
                        if ($competition) {
                            $competitionsFromSeasons->put($competition->id, $competition);
                        }
                    }
                }

                $uniqueCompetitions = $competitionsFromSeasons->values()->sortBy('title');

                // Добавить информацию о сезонах для каждого соревнования
                foreach ($uniqueCompetitions as $competition) {
                    $competitionSeasons = $uniqueSeasons->where('competition_id', $competition->id);
                    $competition->seasons_info = $competitionSeasons->map(function($season) {
                        return [
                            'id' => $season->id,
                            'name' => $season->name,
                            'title' => $season->title,
                            'date_from' => $season->date_from,
                            'date_to' => $season->date_to
                        ];
                    })->values();
                }
            }

            // Если нет сезонов, создаем виртуальный сезон "Все время"
            if ($uniqueSeasons->isEmpty()) {
                $virtualSeason = (object) [
                    'id' => 'all',
                    'title' => 'Все время',
                    'display_name' => 'Все время',
                    'date_from' => null,
                    'date_to' => null,
                    'competition_id' => null,
                    'is_virtual' => true
                ];
                $uniqueSeasons = collect([$virtualSeason]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'seasons' => $uniqueSeasons->values(),
                    'competitions' => $uniqueCompetitions->values()
                ],
                'message' => 'Данные успешно получены'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить общую статистику конкретного игрока
     */
    public function getPersonStatsOverall($personId): JsonResponse
    {
        try {
            // Получить все события игрока с действиями
            $events = Event::whereHas('actions', function($query) use ($personId) {
                    $query->where('person_id', $personId);
                })
                ->orWhereHas('lineups', function($query) use ($personId) {
                    $query->where('person_id', $personId);
                })
                ->with([
                    'actions' => function($query) use ($personId) {
                        $query->where('person_id', $personId)
                              ->with(['actionType', 'club.city']);
                    },
                    'lineups' => function($query) use ($personId) {
                        $query->where('person_id', $personId);
                    },
                    'competition.seasons'
                ])
                ->get();

            $playerStats = [];
            $playerStatsByClub = [];
            $playerMatches = [];
            $playerSeasons = [];
            $playerCompetitions = [];

            // Подсчитать матчи, сезоны и соревнования
            foreach ($events as $event) {
                // Определить сезон для события
                $eventSeason = null;
                if ($event->competition && $event->date_from) {
                    $eventSeason = $event->competition->seasons()
                        ->where('date_from', '<=', $event->date_from)
                        ->where('date_to', '>=', $event->date_from)
                        ->first();
                }

                // Подсчитать матчи только если игрок был в составе
                if ($event->lineups->count() > 0) {
                    if (!in_array($event->id, $playerMatches)) {
                        $playerMatches[] = $event->id;
                    }
                }

                // Подсчитать сезоны
                if ($eventSeason && !in_array($eventSeason->id, $playerSeasons)) {
                    $playerSeasons[] = $eventSeason->id;
                }

                // Подсчитать соревнования
                if ($event->competition && !in_array($event->competition->id, $playerCompetitions)) {
                    $playerCompetitions[] = $event->competition->id;
                }

                // Подсчитать действия
                foreach ($event->actions as $action) {
                    $actionType = $action->actionType->name;
                    $club = $action->club;
                    $clubKey = $club ? $club->id : 0;
                    $clubInfo = $club ? [
                        'id' => $club->id,
                        'title' => $club->title,
                        'image_path' => $club->club_image_path,
                        'city' => $club->city ? $club->city->title : null
                    ] : null;

                    // Общая статистика
                    if (!isset($playerStats[$actionType])) {
                        $playerStats[$actionType] = 0;
                    }

                    if ($action->actionType->group == 2) {
                        $playerStats[$actionType] += $action->value ?? 0;
                    } else {
                        $playerStats[$actionType]++;
                    }

                    // Статистика по клубам
                    if (!isset($playerStatsByClub[$actionType])) {
                        $playerStatsByClub[$actionType] = [];
                    }
                    if (!isset($playerStatsByClub[$actionType][$clubKey])) {
                        $playerStatsByClub[$actionType][$clubKey] = [
                            'count' => 0,
                            'club' => $clubInfo
                        ];
                    }
                    if ($action->actionType->group == 2) {
                        $playerStatsByClub[$actionType][$clubKey]['count'] += $action->value ?? 0;
                    } else {
                        $playerStatsByClub[$actionType][$clubKey]['count']++;
                    }

                    // Голы всего (по клубам)
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats['Голы всего'])) {
                            $playerStats['Голы всего'] = 0;
                        }
                        $playerStats['Голы всего'] += $action->value ?? 1;
                        if (!isset($playerStatsByClub['Голы всего'])) {
                            $playerStatsByClub['Голы всего'] = [];
                        }
                        if (!isset($playerStatsByClub['Голы всего'][$clubKey])) {
                            $playerStatsByClub['Голы всего'][$clubKey] = [
                                'count' => 0,
                                'club' => $clubInfo
                            ];
                        }
                        $playerStatsByClub['Голы всего'][$clubKey]['count'] += $action->value ?? 1;
                    }
                }
            }

            // Собрать информацию о типах действий
            $actionTypesInfo = [];
            foreach ($playerStats as $actionName => $count) {
                // Специальная обработка для поля "Голы всего"
                if ($actionName === 'Голы всего') {
                    $actionTypesInfo[$actionName] = [
                        'short_name' => 'Голы всего',
                        'short_name_table' => 'Голы всего',
                        'icon' => 'heroicons:fire',
                        'color' => 'text-red-500',
                        'full_name' => 'голы всего'
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

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $playerStats,
                    'statistics_by_club' => $playerStatsByClub,
                    'action_types' => $actionTypesInfo,
                    'total_matches' => count($playerMatches),
                    'total_seasons' => count($playerSeasons),
                    'total_competitions' => count($playerCompetitions),
                    'total_events' => $events->count()
                ],
                'message' => 'Общая статистика игрока успешно получена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении общей статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику конкретного игрока по сезону
     */
    public function getPersonStatsBySeason($personId, $seasonId): JsonResponse
    {
        try {
            // Если это виртуальный сезон "Все время"
            if ($seasonId === 'all') {
                return $this->getPersonStatsOverall($personId);
            }

            // Получаем сезон
            $season = CompetitionSeason::find($seasonId);
            if (!$season) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не найден'
                ], 404);
            }

            // Получить события игрока для данного сезона
            $events = Event::where('competition_id', $season->competition_id)
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
                    }
                ])
                ->get();

            $playerStats = [];
            $playerStatsByClub = [];
            $playerMatches = [];

            // Подсчитать матчи только если игрок был в составе
            foreach ($events as $event) {
                if ($event->lineups->count() > 0) {
                    if (!in_array($event->id, $playerMatches)) {
                        $playerMatches[] = $event->id;
                    }
                }

                // Подсчитать действия
                foreach ($event->actions as $action) {
                    $actionType = $action->actionType->name;
                    $club = $action->club;
                    $clubKey = $club ? $club->id : 0;
                    $clubInfo = $club ? [
                        'id' => $club->id,
                        'title' => $club->title,
                        'image_path' => $club->club_image_path,
                        'city' => $club->city ? $club->city->title : null
                    ] : null;

                    // Общая статистика
                    if (!isset($playerStats[$actionType])) {
                        $playerStats[$actionType] = 0;
                    }

                    if ($action->actionType->group == 2) {
                        $playerStats[$actionType] += $action->value ?? 0;
                    } else {
                        $playerStats[$actionType]++;
                    }

                    // Статистика по клубам
                    if (!isset($playerStatsByClub[$actionType])) {
                        $playerStatsByClub[$actionType] = [];
                    }
                    if (!isset($playerStatsByClub[$actionType][$clubKey])) {
                        $playerStatsByClub[$actionType][$clubKey] = [
                            'count' => 0,
                            'club' => $clubInfo
                        ];
                    }
                    if ($action->actionType->group == 2) {
                        $playerStatsByClub[$actionType][$clubKey]['count'] += $action->value ?? 0;
                    } else {
                        $playerStatsByClub[$actionType][$clubKey]['count']++;
                    }

                    // Голы всего (по клубам)
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats['Голы всего'])) {
                            $playerStats['Голы всего'] = 0;
                        }
                        $playerStats['Голы всего'] += $action->value ?? 1;
                        if (!isset($playerStatsByClub['Голы всего'])) {
                            $playerStatsByClub['Голы всего'] = [];
                        }
                        if (!isset($playerStatsByClub['Голы всего'][$clubKey])) {
                            $playerStatsByClub['Голы всего'][$clubKey] = [
                                'count' => 0,
                                'club' => $clubInfo
                            ];
                        }
                        $playerStatsByClub['Голы всего'][$clubKey]['count'] += $action->value ?? 1;
                    }
                }
            }

            // Собрать информацию о типах действий
            $actionTypesInfo = [];
            foreach ($playerStats as $actionName => $count) {
                // Специальная обработка для поля "Голы всего"
                if ($actionName === 'Голы всего') {
                    $actionTypesInfo[$actionName] = [
                        'short_name' => 'Голы всего',
                        'short_name_table' => 'Голы всего',
                        'icon' => 'heroicons:fire',
                        'color' => 'text-red-500',
                        'full_name' => 'голы всего'
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
                    'statistics' => $playerStats,
                    'statistics_by_club' => $playerStatsByClub,
                    'action_types' => $actionTypesInfo,
                    'total_matches' => count($playerMatches),
                    'total_events' => $events->count()
                ],
                'message' => 'Статистика игрока по сезону успешно получена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику конкретного игрока по соревнованию
     */
    public function getPersonStatsByCompetition($personId, $competitionId): JsonResponse
    {
        try {
            // Получаем соревнование
            $competition = \App\Models\Competition::find($competitionId);
            if (!$competition) {
                return response()->json([
                    'success' => false,
                    'message' => 'Соревнование не найдено'
                ], 404);
            }

            // Получить события игрока для данного соревнования
            $events = Event::where('competition_id', $competition->id)
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
                    }
                ])
                ->get();

            $playerStats = [];
            $playerStatsByClub = [];
            $playerMatches = [];

            // Подсчитать матчи только если игрок был в составе
            foreach ($events as $event) {
                if ($event->lineups->count() > 0) {
                    if (!in_array($event->id, $playerMatches)) {
                        $playerMatches[] = $event->id;
                    }
                }

                // Подсчитать действия
                foreach ($event->actions as $action) {
                    $actionType = $action->actionType->name;
                    $club = $action->club;
                    $clubKey = $club ? $club->id : 0;
                    $clubInfo = $club ? [
                        'id' => $club->id,
                        'title' => $club->title,
                        'image_path' => $club->club_image_path,
                        'city' => $club->city ? $club->city->title : null
                    ] : null;

                    // Общая статистика
                    if (!isset($playerStats[$actionType])) {
                        $playerStats[$actionType] = 0;
                    }

                    if ($action->actionType->group == 2) {
                        $playerStats[$actionType] += $action->value ?? 0;
                    } else {
                        $playerStats[$actionType]++;
                    }

                    // Статистика по клубам
                    if (!isset($playerStatsByClub[$actionType])) {
                        $playerStatsByClub[$actionType] = [];
                    }
                    if (!isset($playerStatsByClub[$actionType][$clubKey])) {
                        $playerStatsByClub[$actionType][$clubKey] = [
                            'count' => 0,
                            'club' => $clubInfo
                        ];
                    }
                    if ($action->actionType->group == 2) {
                        $playerStatsByClub[$actionType][$clubKey]['count'] += $action->value ?? 0;
                    } else {
                        $playerStatsByClub[$actionType][$clubKey]['count']++;
                    }

                    // Голы всего (по клубам)
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats['Голы всего'])) {
                            $playerStats['Голы всего'] = 0;
                        }
                        $playerStats['Голы всего'] += $action->value ?? 1;
                        if (!isset($playerStatsByClub['Голы всего'])) {
                            $playerStatsByClub['Голы всего'] = [];
                        }
                        if (!isset($playerStatsByClub['Голы всего'][$clubKey])) {
                            $playerStatsByClub['Голы всего'][$clubKey] = [
                                'count' => 0,
                                'club' => $clubInfo
                            ];
                        }
                        $playerStatsByClub['Голы всего'][$clubKey]['count'] += $action->value ?? 1;
                    }
                }
            }

            // Собрать информацию о типах действий
            $actionTypesInfo = [];
            foreach ($playerStats as $actionName => $count) {
                // Специальная обработка для поля "Голы всего"
                if ($actionName === 'Голы всего') {
                    $actionTypesInfo[$actionName] = [
                        'short_name' => 'Голы всего',
                        'short_name_table' => 'Голы всего',
                        'icon' => 'heroicons:fire',
                        'color' => 'text-red-500',
                        'full_name' => 'голы всего'
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

            return response()->json([
                'success' => true,
                'data' => [
                    'season' => [
                        'id' => null,
                        'name' => null,
                        'date_from' => null,
                        'date_to' => null,
                        'competition' => [
                            'id' => $competition->id,
                            'title' => $competition->title
                        ]
                    ],
                    'statistics' => $playerStats,
                    'statistics_by_club' => $playerStatsByClub,
                    'action_types' => $actionTypesInfo,
                    'total_matches' => count($playerMatches),
                    'total_events' => $events->count()
                ],
                'message' => 'Статистика игрока по соревнованию успешно получена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику конкретного игрока по сезону и соревнованию
     */
    public function getPersonStatsBySeasonAndCompetition($personId, $seasonId, $competitionId): JsonResponse
    {
        try {
            Log::info("getPersonStatsBySeasonAndCompetition called with personId: $personId, seasonId: $seasonId, competitionId: $competitionId");

            // Получаем сезон
            $season = CompetitionSeason::find($seasonId);
            if (!$season) {
                Log::error("Season not found: $seasonId");
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не найден'
                ], 404);
            }
            Log::info("Season found: " . $season->name . " (competition_id: " . $season->competition_id . ")");

            // Получаем соревнование
            $competition = \App\Models\Competition::find($competitionId);
            if (!$competition) {
                Log::error("Competition not found: $competitionId");
                return response()->json([
                    'success' => false,
                    'message' => 'Соревнование не найдено'
                ], 404);
            }
            Log::info("Competition found: " . $competition->title . " (id: " . $competition->id . ")");

            // Проверяем, что сезон принадлежит данному соревнованию
            if ($season->competition_id != $competition->id) {
                Log::error("Season competition_id ($season->competition_id) doesn't match competition id ($competition->id)");
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не принадлежит данному соревнованию'
                ], 400);
            }

            // Получить события игрока для данного сезона и соревнования
            $events = Event::where('competition_id', $competition->id)
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
                    }
                ])
                ->get();

            Log::info("Found " . $events->count() . " events for person $personId in competition $competitionId");

            $playerStats = [];
            $playerStatsByClub = [];
            $playerMatches = [];

            // Подсчитать матчи только если игрок был в составе
            foreach ($events as $event) {
                if ($event->lineups->count() > 0) {
                    if (!in_array($event->id, $playerMatches)) {
                        $playerMatches[] = $event->id;
                    }
                }

                // Подсчитать действия
                foreach ($event->actions as $action) {
                    $actionType = $action->actionType->name;
                    $club = $action->club;
                    $clubKey = $club ? $club->id : 0;
                    $clubInfo = $club ? [
                        'id' => $club->id,
                        'title' => $club->title,
                        'image_path' => $club->club_image_path,
                        'city' => $club->city ? $club->city->title : null
                    ] : null;
                    // Общая статистика
                    if (!isset($playerStats[$actionType])) {
                        $playerStats[$actionType] = 0;
                    }
                    if ($action->actionType->group == 2) {
                        $playerStats[$actionType] += $action->value ?? 0;
                    } else {
                        $playerStats[$actionType]++;
                    }
                    // Статистика по клубам
                    if (!isset($playerStatsByClub[$actionType])) {
                        $playerStatsByClub[$actionType] = [];
                    }
                    if (!isset($playerStatsByClub[$actionType][$clubKey])) {
                        $playerStatsByClub[$actionType][$clubKey] = [
                            'count' => 0,
                            'club' => $clubInfo
                        ];
                    }
                    if ($action->actionType->group == 2) {
                        $playerStatsByClub[$actionType][$clubKey]['count'] += $action->value ?? 0;
                    } else {
                        $playerStatsByClub[$actionType][$clubKey]['count']++;
                    }
                    // Голы всего (по клубам)
                    if ($action->actionType->group == 1) {
                        if (!isset($playerStats['Голы всего'])) {
                            $playerStats['Голы всего'] = 0;
                        }
                        $playerStats['Голы всего'] += $action->value ?? 1;
                        if (!isset($playerStatsByClub['Голы всего'])) {
                            $playerStatsByClub['Голы всего'] = [];
                        }
                        if (!isset($playerStatsByClub['Голы всего'][$clubKey])) {
                            $playerStatsByClub['Голы всего'][$clubKey] = [
                                'count' => 0,
                                'club' => $clubInfo
                            ];
                        }
                        $playerStatsByClub['Голы всего'][$clubKey]['count'] += $action->value ?? 1;
                    }
                }
            }

            // Собрать информацию о типах действий
            $actionTypesInfo = [];
            foreach ($playerStats as $actionName => $count) {
                // Специальная обработка для поля "Голы всего"
                if ($actionName === 'Голы всего') {
                    $actionTypesInfo[$actionName] = [
                        'short_name' => 'Голы всего',
                        'short_name_table' => 'Голы всего',
                        'icon' => 'heroicons:fire',
                        'color' => 'text-red-500',
                        'full_name' => 'голы всего'
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

            Log::info("Returning statistics with " . count($playerStats) . " stats and " . count($playerMatches) . " matches");

            return response()->json([
                'success' => true,
                'data' => [
                    'season' => [
                        'id' => $season->id,
                        'name' => $season->name,
                        'date_from' => $season->date_from,
                        'date_to' => $season->date_to,
                        'competition' => [
                            'id' => $competition->id,
                            'title' => $competition->title
                        ]
                    ],
                    'statistics' => $playerStats,
                    'statistics_by_club' => $playerStatsByClub,
                    'action_types' => $actionTypesInfo,
                    'total_matches' => count($playerMatches),
                    'total_events' => $events->count()
                ],
                'message' => 'Статистика игрока по сезону и соревнованию успешно получена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики: ' . $e->getMessage()
            ], 500);
        }
    }
}
