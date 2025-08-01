<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Event;
use App\Models\EventAction;
use App\Models\Season;
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
                ->with(['competition.seasons' => function ($query) {
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

                    // Получаем сезон конкретного события из таблицы seasons
                    if ($event->season_id) {
                        $season = Season::with('competitions')->find($event->season_id);
                        if ($season) {
                            Log::info('Добавляем сезон ID: ' . $season->id . ', title: ' . $season->title . ', name: ' . $season->name);
                            $seasons->put($season->id, $season);
                        }
                    }
                } else {
                    Log::info('У события ' . $event->id . ' нет соревнования');
                }
            }

            // Убрать дубликаты сезонов и отсортировать
            $uniqueSeasons = $seasons->values()->sortByDesc('date_from');

            // Убрать дубликаты соревнований и отсортировать
            $uniqueCompetitions = $competitions->values()->sortBy('title');

            // Добавить информацию о сезонах для каждого соревнования
            foreach ($uniqueCompetitions as $competition) {
                // Получаем сезоны для этого соревнования через связь
                $competitionSeasons = $uniqueSeasons->filter(function ($season) use ($competition) {
                    // Проверяем, есть ли связь между сезоном и соревнованием
                    return $season->competitions->contains('id', $competition->id);
                });

                $competition->seasons_info = $competitionSeasons->map(function ($season) {
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
                    // Получаем соревнования для этого сезона
                    $seasonCompetitions = $season->competitions;
                    foreach ($seasonCompetitions as $competition) {
                        $competitionsFromSeasons->put($competition->id, $competition);
                    }
                }

                $uniqueCompetitions = $competitionsFromSeasons->values()->sortBy('title');

                // Добавить информацию о сезонах для каждого соревнования
                foreach ($uniqueCompetitions as $competition) {
                    $competitionSeasons = $uniqueSeasons->filter(function ($season) use ($competition) {
                        return $season->competitions->contains('id', $competition->id);
                    });

                    $competition->seasons_info = $competitionSeasons->map(function ($season) {
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
            Log::info('Сезоны: ' . $uniqueSeasons->pluck('title', 'id')->toJson());
            Log::info('Соревнования: ' . $uniqueCompetitions->pluck('id', 'title')->toJson());

            $response = [
                'success' => true,
                'data' => [
                    'seasons' => $uniqueSeasons->values(),
                    'competitions' => $uniqueCompetitions->values()
                ],
                'message' => 'Сезоны и соревнования успешно получены'
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения сезонов и соревнований: ' . $e->getMessage()
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
            $events = Event::where(function ($query) use ($club) {
                $query->where('club1_id', $club->id)
                    ->orWhere('club2_id', $club->id);
            })
                ->where('competition_id', $competition->id)
                ->with([
                    'actions' => function ($query) use ($club) {
                        $query->where('club_id', $club->id)
                            ->with(['person.activeAmpluaMemberships.amplua', 'person.mainImage', 'actionType']);
                    },
                    'lineups' => function ($query) use ($club) {
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
            usort($result, function ($a, $b) {
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
            $events = Event::where(function ($query) use ($club) {
                $query->where('club1_id', $club->id)
                    ->orWhere('club2_id', $club->id);
            })
                ->where('competition_id', $season->competition_id)
                ->with([
                    'actions' => function ($query) use ($club) {
                        $query->where('club_id', $club->id)
                            ->with(['person.activeAmpluaMemberships.amplua', 'person.mainImage', 'actionType']);
                    },
                    'lineups' => function ($query) use ($club) {
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
            usort($result, function ($a, $b) {
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
                    'actions' => function ($query) use ($club) {
                        $query->where('club_id', $club->id)
                            ->with(['person.activeAmpluaMemberships.amplua', 'person.mainImage', 'actionType']);
                    },
                    'lineups' => function ($query) use ($club) {
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
            usort($result, function ($a, $b) {
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
            Log::info("Получение сезонов для игрока {$personId}");

            // Получаем все события игрока
            $events = Event::where(function ($query) use ($personId) {
                $query->whereHas('actions', function ($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                })
                    ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    });
            })
                ->get();

            Log::info("Найдено событий для игрока {$personId}: " . $events->count());

            // Собираем уникальные соревнования игрока
            $playerCompetitions = collect();
            foreach ($events as $event) {
                if ($event->competition_id) {
                    $playerCompetitions->put($event->competition_id, $event->competition_id);
                    Log::info("Добавлено соревнование: {$event->competition_id}");
                }
            }

            Log::info("Уникальных соревнований игрока: " . $playerCompetitions->count());

            // Получаем сезоны, связанные с соревнованиями игрока
            $playerSeasons = collect();
            $competitions = collect();

            foreach ($playerCompetitions as $competitionId) {
                Log::info("Обрабатываем соревнование: {$competitionId}");

                // Получаем соревнование
                $competition = Competition::find($competitionId);
                if ($competition) {
                    $competitions->put($competition->id, $competition);
                }

                // Получаем сезоны этого соревнования через pivot таблицу
                $competitionSeasons = DB::table('competition_seasons')
                    ->where('competition_id', $competitionId)
                    ->get();

                foreach ($competitionSeasons as $cs) {
                    if ($cs->season_id) {
                        $season = Season::find($cs->season_id);
                        if ($season) {
                            $playerSeasons->put($season->id, $season);
                        }
                    }
                }
            }

            // Сортируем сезоны по дате (новые сначала)
            $sortedSeasons = $playerSeasons->sortByDesc('date_from')->values();
            $sortedCompetitions = $competitions->sortBy('title')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'seasons' => $sortedSeasons,
                    'competitions' => $sortedCompetitions
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка получения сезонов игрока: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка получения сезонов игрока'
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
            $events = Event::whereHas('actions', function ($query) use ($personId) {
                $query->where('person_id', $personId);
            })
                ->orWhereHas('lineups', function ($query) use ($personId) {
                    $query->where('person_id', $personId);
                })
                ->with([
                    'actions' => function ($query) use ($personId) {
                        $query->where('person_id', $personId)
                            ->with(['actionType', 'club.city']);
                    },
                    'lineups' => function ($query) use ($personId) {
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
            Log::info("Получение статистики для игрока {$personId} по сезону {$seasonId}");

            // Находим сезон по ID
            $season = Season::find($seasonId);
            if (!$season) {
                Log::error("Сезон {$seasonId} не найден");
                return response()->json([
                    'success' => false,
                    'error' => 'Сезон не найден'
                ], 404);
            }

            // Получаем все соревнования, связанные с этим сезоном через pivot таблицу
            $competitionIds = DB::table('competition_seasons')
                ->where('season_id', $season->id)
                ->pluck('competition_id');

            if ($competitionIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Соревнования для данного сезона не найдены'
                ], 404);
            }

            // Получаем события игрока в соревнованиях этого сезона
            $events = Event::whereIn('competition_id', $competitionIds)
                ->where(function ($query) use ($personId) {
                    $query->whereHas('actions', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    })
                        ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                            $subQuery->where('person_id', $personId);
                        });
                })
                ->with([
                    'actions.actionType',
                    'actions.club.city',
                    'lineups',
                    'competition'
                ])
                ->get();

            // Инициализируем массивы для статистики
            $playerStats = [];
            $actionTypesInfo = [];
            $playerStatsByClub = [];
            $totalMatches = 0;

            // Обрабатываем каждое событие
            foreach ($events as $event) {
                // Подсчитываем матчи (если игрок в составе)
                if ($event->lineups->where('person_id', $personId)->count() > 0) {
                    $totalMatches++;
                }

                // Обрабатываем действия игрока
                foreach ($event->actions as $action) {
                    if ($action->person_id != $personId) continue;

                    $actionType = $action->actionType;
                    if (!$actionType) continue;

                    $actionName = $actionType->name;
                    if ($actionName === 'ГОЛЫ') {
                        $actionName = 'Голы всего';
                    }

                    // Инициализируем статистику по действию
                    if (!isset($playerStats[$actionName])) {
                        $playerStats[$actionName] = 0;
                        $actionTypesInfo[$actionName] = [
                            'name' => $actionName,
                            'short_name' => $actionType->short_name ?? $actionName,
                            'icon' => $actionType->icon ?? 'heroicons:star',
                            'color' => $actionType->color ?? 'text-gray-600',
                            'full_name' => $actionType->full_name ?? $actionName
                        ];
                    }

                    // Подсчитываем статистику в зависимости от группы
                    if ($actionType->group == 2) {
                        // Для группы 2 - суммируем очки
                        $playerStats[$actionName] += $action->value ?? 0;
                    } else {
                        // Для остальных групп - считаем количество
                        $playerStats[$actionName]++;
                    }

                    // Голы всего (общая статистика)
                    if ($actionType->group == 1) {
                        if (!isset($playerStats['Голы всего'])) {
                            $playerStats['Голы всего'] = 0;
                        }
                        $playerStats['Голы всего'] += $action->value ?? 1;

                        // Добавляем информацию о типе действия для "Голы всего"
                        if (!isset($actionTypesInfo['Голы всего'])) {
                            $actionTypesInfo['Голы всего'] = [
                                'name' => 'Голы всего',
                                'short_name' => 'Голы всего',
                                'icon' => 'heroicons:fire',
                                'color' => 'text-red-500',
                                'full_name' => 'голы всего'
                            ];
                        }
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
                                    'image_path' => $action->club->club_image_path,
                                    'city' => $action->club->city ? $action->club->city->title : null
                                ]
                            ];
                        }

                        // Подсчитываем статистику по клубам в зависимости от группы
                        if ($actionType->group == 2) {
                            $playerStatsByClub[$actionName][$clubKey]['count'] += $action->value ?? 0;
                        } else {
                            $playerStatsByClub[$actionName][$clubKey]['count']++;
                        }

                        // Голы всего (по клубам)
                        if ($actionType->group == 1) {
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
                                    'club' => [
                                        'id' => $action->club->id,
                                        'title' => $action->club->title,
                                        'image_path' => $action->club->club_image_path,
                                        'city' => $action->club->city ? $action->club->city->title : null
                                    ]
                                ];
                            }
                            $playerStatsByClub['Голы всего'][$clubKey]['count'] += $action->value ?? 1;
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $playerStats,
                    'action_types' => $actionTypesInfo,
                    'statistics_by_club' => $playerStatsByClub,
                    'total_matches' => $totalMatches,
                    'total_seasons' => 1,
                    'total_competitions' => $competitionIds->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка получения статистики по сезону: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка получения статистики по сезону'
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
                ->where(function ($query) use ($personId) {
                    $query->whereHas('actions', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    })
                        ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                            $subQuery->where('person_id', $personId);
                        });
                })
                ->with([
                    'actions' => function ($query) use ($personId) {
                        $query->where('person_id', $personId)
                            ->with(['actionType', 'club.city']);
                    },
                    'lineups' => function ($query) use ($personId) {
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
            // Получаем сезон
            $season = Season::find($seasonId);
            if (!$season) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не найден'
                ], 404);
            }

            // Получаем соревнование
            $competition = \App\Models\Competition::find($competitionId);
            if (!$competition) {
                return response()->json([
                    'success' => false,
                    'message' => 'Соревнование не найдено'
                ], 404);
            }

            // Проверяем, что сезон связан с данным соревнованием через pivot таблицу
            $competitionSeason = DB::table('competition_seasons')
                ->where('season_id', $season->id)
                ->where('competition_id', $competition->id)
                ->first();

            if (!$competitionSeason) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сезон не связан с данным соревнованием'
                ], 400);
            }

            // Получить события игрока для данного сезона и соревнования
            $events = Event::where('competition_id', $competition->id)
                ->where(function ($query) use ($personId) {
                    $query->whereHas('actions', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    })
                        ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                            $subQuery->where('person_id', $personId);
                        });
                })
                ->with([
                    'actions' => function ($query) use ($personId) {
                        $query->where('person_id', $personId)
                            ->with(['actionType', 'club.city']);
                    },
                    'lineups' => function ($query) use ($personId) {
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

    public function getPersonStatsBySeasonTitle($personId, $seasonTitle): JsonResponse
    {
        try {
            // Находим сезон по названию
            $season = Season::where('title', $seasonTitle)->first();
            if (!$season) {
                return response()->json([
                    'success' => false,
                    'error' => 'Сезон не найден'
                ], 404);
            }

            // Получаем все соревнования, связанные с этим сезоном через pivot таблицу
            $competitionIds = DB::table('competition_seasons')
                ->where('season_id', $season->id)
                ->pluck('competition_id');

            if ($competitionIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Соревнования для данного сезона не найдены'
                ], 404);
            }

            // Получаем события для всех соревнований этого сезона
            $events = Event::where(function ($query) use ($personId) {
                $query->whereHas('actions', function ($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                })
                    ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    });
            })
                ->whereIn('competition_id', $competitionIds)
                ->with([
                    'actions.actionType',
                    'actions.club.city',
                    'lineups',
                    'competition'
                ])
                ->get();

            // Инициализируем массивы для статистики
            $playerStats = [];
            $actionTypesInfo = [];
            $playerStatsByClub = [];
            $totalMatches = 0;

            // Обрабатываем каждое событие
            foreach ($events as $event) {
                // Подсчитываем матчи (если игрок в составе)
                if ($event->lineups->where('person_id', $personId)->count() > 0) {
                    $totalMatches++;
                }

                // Обрабатываем действия игрока
                foreach ($event->actions as $action) {
                    if ($action->person_id != $personId) continue;

                    $actionType = $action->actionType;
                    if (!$actionType) continue;

                    $actionName = $actionType->name;
                    if ($actionName === 'ГОЛЫ') {
                        $actionName = 'Голы всего';
                    }

                    // Инициализируем статистику по действию
                    if (!isset($playerStats[$actionName])) {
                        $playerStats[$actionName] = 0;
                        $actionTypesInfo[$actionName] = [
                            'name' => $actionType->name,
                            'short_name' => $actionType->short_name ?? $actionName,
                            'icon' => $actionType->icon ?? 'heroicons:star',
                            'color' => $actionType->color ?? 'text-gray-600',
                            'full_name' => $actionType->full_name ?? $actionName
                        ];
                    }

                    // Подсчитываем статистику в зависимости от группы
                    if ($actionType->group === 2) {
                        // Для группы 2 - суммируем очки
                        $playerStats[$actionName] += $action->value ?? 0;
                    } else {
                        // Для остальных групп - считаем количество
                        $playerStats[$actionName]++;
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
                                    'image_path' => $action->club->club_image_path,
                                    'city' => $action->club->city ? $action->club->city->title : null
                                ]
                            ];
                        }

                        // Подсчитываем статистику по клубам в зависимости от группы
                        if ($actionType->group === 2) {
                            $playerStatsByClub[$actionName][$clubKey]['count'] += $action->value ?? 0;
                        } else {
                            $playerStatsByClub[$actionName][$clubKey]['count']++;
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $playerStats,
                    'action_types' => $actionTypesInfo,
                    'statistics_by_club' => $playerStatsByClub,
                    'total_matches' => $totalMatches,
                    'total_seasons' => 1,
                    'total_competitions' => $competitionIds->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка получения статистики по сезону: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка получения статистики по сезону'
            ], 500);
        }
    }

    /**
     * Получить соревнования игрока по сезону
     */
    public function getPersonCompetitionsBySeason($personId, $seasonId): JsonResponse
    {
        try {
            // Находим сезон по ID
            $season = Season::find($seasonId);
            if (!$season) {
                return response()->json([
                    'success' => false,
                    'error' => 'Сезон не найден'
                ], 404);
            }

            // Получаем все соревнования, связанные с этим сезоном через pivot таблицу
            $competitionIds = DB::table('competition_seasons')
                ->where('season_id', $season->id)
                ->pluck('competition_id');

            if ($competitionIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Соревнования для данного сезона не найдены'
                ], 404);
            }

            // Получаем события игрока в соревнованиях этого сезона
            $events = Event::whereIn('competition_id', $competitionIds)
                ->where(function ($query) use ($personId) {
                    $query->whereHas('actions', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    })
                        ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                            $subQuery->where('person_id', $personId);
                        });
                })
                ->with(['competition'])
                ->get();

            // Собираем уникальные соревнования, в которых есть события игрока
            $competitions = collect();
            foreach ($events as $event) {
                if ($event->competition && !$competitions->contains('id', $event->competition->id)) {
                    $competitions->push($event->competition);
                }
            }

            // Сортируем соревнования по названию
            $sortedCompetitions = $competitions->sortBy('title')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'competitions' => $sortedCompetitions
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка получения соревнований игрока по сезону: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка получения соревнований игрока по сезону'
            ], 500);
        }
    }

    /**
     * Получить все соревнования игрока
     */
    public function getPersonCompetitions($personId): JsonResponse
    {
        try {
            // Получаем события игрока
            $events = Event::where(function ($query) use ($personId) {
                $query->whereHas('actions', function ($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                })
                    ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    });
            })
                ->with(['competition'])
                ->get();

            // Собираем уникальные соревнования, в которых есть события игрока
            $competitions = collect();
            foreach ($events as $event) {
                if ($event->competition && !$competitions->contains('id', $event->competition->id)) {
                    $competitions->push($event->competition);
                }
            }

            // Сортируем соревнования по названию
            $sortedCompetitions = $competitions->sortBy('title')->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'competitions' => $sortedCompetitions
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка получения соревнований игрока: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка получения соревнований игрока'
            ], 500);
        }
    }



    /**
     * Получить матчи игрока
     */
    public function getPersonMatches($personId, Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);

            // Базовый запрос для получения событий игрока
            $query = Event::where(function ($query) use ($personId) {
                $query->whereHas('actions', function ($subQuery) use ($personId) {
                    $subQuery->where('person_id', $personId);
                })
                    ->orWhereHas('lineups', function ($subQuery) use ($personId) {
                        $subQuery->where('person_id', $personId);
                    });
            })
                ->whereNotNull('result')
                ->where('result', '!=', '')
                ->with([
                    'actions' => function ($query) use ($personId) {
                        $query->where('person_id', $personId)
                            ->with(['actionType']);
                    },
                    'lineups' => function ($query) use ($personId) {
                        $query->where('person_id', $personId);
                    },
                    'competition',
                    'club1.city',
                    'club2.city'
                ]);

            // Получаем общее количество событий
            $totalEvents = $query->count();

            // Применяем пагинацию
            $events = $query->orderBy('date_from', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            // Формируем матчи с событиями игрока
            $matches = [];
            $actionTypesInfo = [];

            foreach ($events as $event) {
                $playerEvents = [];

                // Собираем события игрока в этом матче
                foreach ($event->actions as $action) {
                    $actionTypeName = $action->actionType->name;

                    // Собираем информацию о типах действий
                    if (!isset($actionTypesInfo[$actionTypeName])) {
                        $actionType = $action->actionType;
                        $actionTypesInfo[$actionTypeName] = [
                            'short_name' => $actionType->short_name ?: $actionType->name,
                            'short_name_table' => $actionType->short_name_table ?: $actionType->short_name ?: $actionType->name,
                            'icon' => $actionType->icon,
                            'color' => $actionType->color,
                            'full_name' => $actionType->name,
                            'group' => $actionType->group
                        ];
                    }



                    $playerEvents[] = [
                        'id' => $action->id,
                        'action_type' => $actionTypeName,
                        'minute' => $action->minute,
                        'value' => $action->value ?? 1
                    ];
                }

                // Определяем команды
                $homeTeam = $event->club1;
                $awayTeam = $event->club2;

                // Обработка результата матча
                $result = $event->result;
                $homeScore = null;
                $awayScore = null;

                if ($result && !empty(trim($result))) {
                    $cleanResult = trim($result);

                    // Пробуем разные разделители
                    if (strpos($cleanResult, '-') !== false) {
                        $scores = explode('-', $cleanResult);
                        if (count($scores) >= 2) {
                            $homeScore = trim($scores[0]);
                            $awayScore = trim($scores[1]);
                        }
                    } elseif (strpos($cleanResult, ':') !== false) {
                        $scores = explode(':', $cleanResult);
                        if (count($scores) >= 2) {
                            $homeScore = trim($scores[0]);
                            $awayScore = trim($scores[1]);
                        }
                    }
                }

                $matches[] = [
                    'id' => $event->id,
                    'date' => $event->date_from,
                    'home_team' => $homeTeam ? [
                        'id' => $homeTeam->id,
                        'title' => $homeTeam->title,
                        'slug' => $homeTeam->slug,
                        'image_path' => $homeTeam->club_image_path,
                        'city' => $homeTeam->city?->title
                    ] : null,
                    'away_team' => $awayTeam ? [
                        'id' => $awayTeam->id,
                        'title' => $awayTeam->title,
                        'slug' => $awayTeam->slug,
                        'image_path' => $awayTeam->club_image_path,
                        'city' => $awayTeam->city?->title
                    ] : null,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'player_events' => $playerEvents,
                    'competition' => $event->competition ? [
                        'id' => $event->competition->id,
                        'title' => $event->competition->title
                    ] : null
                ];
            }







            return response()->json([
                'success' => true,
                'data' => [
                    'matches' => $matches,
                    'action_types' => $actionTypesInfo,
                    'total' => $totalEvents,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalEvents / $perPage)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка получения матчей игрока: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка получения матчей игрока'
            ], 500);
        }
    }
}
