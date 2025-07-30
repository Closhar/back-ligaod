<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Gender;
use App\Models\Series;
use App\Traits\SeriesCountTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiEventController extends Controller
{
    use SeriesCountTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        // Получаем параметры фильтрации из запроса с защитой от больших значений
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $getEvents = $request->input('get_events');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $sportSlug = $request->input('sport');
        $sportId = $request->input('sport_id');
        $clubSlug = $request->input('club');
        $clubId = $request->input('club_id');
        $arenaSlug = $request->input('arena');
        $arenaId = $request->input('arena_id');
        $competitionId = $request->input('competition_id');
        $genderId = $request->input('gender_id');
        $show = $request->input('show'); // По умолчанию показываем события с date_from >= сегодня
        $searchQuery = $request->input('q'); // Параметр поиска
        $show_concrete_date = false; // индикатор, что фильтруем по конкретной дате - при true игнорируется фильтр ВРЕМЕННОЙ ПРОМЕЖУТОК
        $regionId = $request->input('region_id', 1);

        // Преобразуем region_id в числовое значение
        if (is_string($regionId)) {
            $regionId = is_numeric($regionId) ? (int)$regionId : 1;
        }

        // Защита от слишком больших значений per_page и get_events
        $maxPerPage = 100; // Максимальное количество записей на страницу
        $maxGetEvents = 1000; // Максимальное количество записей для get_events

        if ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }
        if ($perPage < 1) {
            $perPage = 10;
        }

        if ($getEvents !== null) {
            $getEvents = (int)$getEvents;
            if ($getEvents > $maxGetEvents) {
                $getEvents = $maxGetEvents;
            }
            if ($getEvents < 1) {
                $getEvents = 10;
            }
        }

        $is_active = $request->input('is_active', 1);
        $show_native = $request->input('show_native'); // Показывать события с командой с regionID независимо от региона события
        $show_home = $request->input('show_home'); // Параметр для фильтрации по региону
        $seriesId = $request->input('series_id'); // Добавляем получение series_id
        $sportPropertyId = $request->input('sport_property_id'); // Добавляем получение sport_property_id
        $tickets = $request->input('tickets'); // Параметр фильтрации по типу билетов (free/paid)
        $matchType = $request->input('match_type'); // Параметр фильтрации по типу матчей (home/away/all)

        $sportSlugItem = $request->input('sport_item');
        $arenaSlugItem = $request->input('arena_item');
        $clubSlugItem = $request->input('club_item');

        $allRegions = $request->input('all_regions');
        // Преобразуем all_regions в boolean
        if ($allRegions !== null) {
            $allRegions = filter_var($allRegions, FILTER_VALIDATE_BOOLEAN);
        }

        if ($sportSlugItem) $sportSlug = $sportSlugItem;
        if ($arenaSlugItem) $arenaSlug = $arenaSlugItem;
        if ($clubSlugItem) $clubSlug = $clubSlugItem;

        $sort = $request->input('sort'); // Новый параметр сортировки

        // Основной запрос с фильтрацией
        $query = Event::query()
            ->select('id', 'title', 'date_from', 'date_to', 'result', 'result_dop', 'image', 'competition_id', 'arena_id', 'club1_id', 'club2_id', 'region_id', 'is_active', 'event_name', 'series_id', 'series_count', 'about', 'tickets', 'report', 'free_tickets')
            ->with([
                'region' => function ($query) {
                    $query->select(['id', 'title', 'title_short']);
                },
                'streams',
                'series' => function ($query) {
                    $query->select(['id','series_type_id', 'description'])
                        ->with(['events' => function ($query) {
                            $query->select(['id', 'event_name']);
                        }]);
                },
                'competition' => function ($query) {
                    $query->select([
                        'id',
                        'title',
                        'title_short',
                        'image',
                        'bg_image',
                        'sport_id',
                        'gender_id',
                        'about',
                        'parse_table_id'
                    ])->with(['sport', 'gender']);
                },
                'club1' => function ($query) {
                    $query->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.region_id',
                        'image' => function ($query) {
                            $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', clubs.image) AS full_image_path"));
                        },
                        'clubs.slug',
                        'clubs.city_id',
                        'clubs.age_id',
                        'clubs.gender_id',
                        'clubs.sport_id'
                    ])->with([
                        'sport',
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title']);
                        },
                        'age' => function ($ageQuery) {
                            $ageQuery->select(['id', 'title_short']);
                        },
                        'gender' => function ($genderQuery) {
                            $genderQuery->select(['id', 'title', 'icon']);
                        }
                    ]);
                },
                'club2' => function ($query) {
                    $query->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.region_id',
                        'image' => function ($query) {
                            $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', clubs.image) AS full_image_path"));
                        },
                        'clubs.slug',
                        'clubs.city_id',
                        'clubs.age_id',
                        'clubs.gender_id',
                        'clubs.sport_id'
                    ])->with([
                        'sport',
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title']);
                        },
                        'age' => function ($ageQuery) {
                            $ageQuery->select(['id', 'title_short']);
                        },
                        'gender' => function ($genderQuery) {
                            $genderQuery->select(['id', 'title', 'icon']);
                        }
                    ]);
                },
                'arena' => function ($query) {
                    $query->select([
                        'id',
                        'title',
                        'city_id',
                        'slug',
                        'map',
                        'address',
                        'sites',
                        'phones',
                        'vks',
                        'latitude',
                        'longitude',
                        'arena_image' => function ($query) {
                            $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', arenas.image) AS full_image_path"));
                        },
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title']);
                        }
                    ]);
                },
            ]);

        if ($regionId && !$allRegions) {
            // Если указан параметр tickets, показываем только события домашнего региона
            if ($tickets) {
                $query->where('region_id', $regionId);
            } else {
                if ($show_home) {
                    if ($show_home === 1) {
                        $query->where('region_id', $regionId);
                    } elseif ($show_home === 2) {
                        $query->where(function($q) use ($regionId) {
                            $q->where('events.region_id', '!=', $regionId)
                              ->orWhereNull('events.region_id');
                        })
                        ->join('clubs as club1', 'events.club1_id', '=', 'club1.id')
                        ->join('clubs as club2', 'events.club2_id', '=', 'club2.id')
                        ->where(function($q) use ($regionId) {
                            $q->where('club1.region_id', $regionId)
                              ->orWhere('club2.region_id', $regionId);
                        })
                        ->select('events.*');
                    }
                } elseif ($show_native) {
                    $query->where(function($q) use ($regionId) {
                        $q->where('region_id', $regionId)
                          ->orWhereHas('club1', function($clubQuery) use ($regionId) {
                              $clubQuery->where('region_id', $regionId);
                          })
                          ->orWhereHas('club2', function($clubQuery) use ($regionId) {
                              $clubQuery->where('region_id', $regionId);
                          });
                    });
                } else {
                    $query->where('region_id', $regionId);
                }
            }
        }

        if ($is_active) {
            $query->where('is_active', $is_active);
        }

        // Применяем фильтр по date_from и date_to
        if ($dateFrom && $dateTo) {
            // Фильтруем по диапазону дат, игнорируя время
            $query->whereDate('date_from', '>=', $dateFrom)
                ->whereDate('date_from', '<=', $dateTo);
        } elseif ($dateFrom) {
            // Фильтруем по точному совпадению даты, игнорируя время
            $query->whereDate('date_from', '=', $dateFrom);
            $show_concrete_date = true;
        } elseif ($dateTo) {
            // Фильтруем по date_from <= date_to, игнорируя время
            $query->whereDate('date_from', '<=', $dateTo);
        }


        // Добавляем фильтр по series_id
        if ($seriesId) {
            $query->where('series_id', $seriesId);
            $show_concrete_date = false;
            $show = 4;
        }

        // Применяем фильтр по show или фильтр по умолчанию
        if (!$show_concrete_date) {
            $today = now()->toDateString(); // Сегодняшняя дата в формате 'Y-m-d'

            if ($show !== null) {
                // Если show указан, применяем соответствующий фильтр
                switch ($show) {
                    case 1: // date_from >= сегодня
                        $query->whereDate('date_from', '>=', $today);
                        break;
                    case 2: // date_from <= сегодня ИЛИ date_to >= сегодня (события, которые начались до сегодня и еще продолжаются)
                        {
                            $query->where(function ($q) use ($today) {
                                $q->whereDate('date_from', '<=', $today);
                            });
                            $sort = "date_from_desc";
                        }
                        break;
                    case 3: // date_from = сегодня
                        $query->whereDate('date_from', '=', $today); // Фильтр по сегодняшней дате, игнорируя время
                        break;
                    case 4: // Без ограничений по date_from
                        break;
                    default:
                        $query->whereDate('date_from', '>=', $today); // По умолчанию для show
                        break;
                }
            } else {
                // Если show не указан, применяем фильтр по умолчанию (будущие события)
                $query->whereDate('date_from', '>=', $today);
            }
        }

        // Применяем фильтры по sport, club, arena
        if ($sportSlug) {
            $query->whereHas('competition.sport', function ($q) use ($sportSlug) {
                $q->where('slug', $sportSlug);
            });
        }
        if ($sportId) {
            $query->whereHas('competition.sport', function ($q) use ($sportId) {
                $q->where('id', $sportId);
            });
        }

        if ($clubSlug) {
            $query->where(function ($q) use ($clubSlug) {
                $q->whereHas('club1', function ($clubQuery) use ($clubSlug) {
                    $clubQuery->where('slug', $clubSlug);
                })->orWhereHas('club2', function ($clubQuery) use ($clubSlug) {
                    $clubQuery->where('slug', $clubSlug);
                });
            });
        }
        if ($clubId) {
            $query->where(function ($q) use ($clubId) {
                $q->whereHas('club1', function ($clubQuery) use ($clubId) {
                    $clubQuery->where('id', $clubId);
                })->orWhereHas('club2', function ($clubQuery) use ($clubId) {
                    $clubQuery->where('id', $clubId);
                });
            });
        }

        if ($arenaSlug) {
            $query->whereHas('arena', function ($q) use ($arenaSlug) {
                $q->where('slug', $arenaSlug);
            });
        }
        if ($arenaId) {
            $query->where('arena_id', $arenaId);
        }

        if ($competitionId) {
            $query->where('competition_id', $competitionId);
        }
        if ($genderId) {
            $query->whereHas('competition', function ($q) use ($genderId) {
                $q->where('gender_id', $genderId); // Фильтр по gender_id в таблице competitions
            });
        }

        // Добавляем фильтр по sport_property_id
        if ($sportPropertyId) {
            $query->whereHas('competition.sport.sport_properties', function ($q) use ($sportPropertyId) {
                $q->where('sport_properties.id', $sportPropertyId);
            });
        }

        // Добавляем фильтр по типу билетов
        if ($tickets) {
            if ($tickets === 'free') {
                $query->where('free_tickets', true);
            } elseif ($tickets === 'paid') {
                $query->where('free_tickets', false);
            }
        }

        // Добавляем фильтр по типу матчей (домашние/выездные)
        if ($matchType && $matchType !== 'all') {
            if ($matchType === 'home') {
                // Домашние матчи - где region_id события равен домашнему региону
                $query->where('region_id', $regionId);
            } elseif ($matchType === 'away') {
                // Выездные матчи - где region_id события НЕ равен домашнему региону или NULL
                $query->where(function($q) use ($regionId) {
                    $q->where('region_id', '!=', $regionId)
                      ->orWhereNull('region_id');
                });
            }
        }

        // Применяем поиск по параметру q
        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('title', 'LIKE', "%{$searchQuery}%")
                    ->orWhereHas('competition', function ($clubQuery) use ($searchQuery) {
                        $clubQuery->where('title', 'LIKE', "%{$searchQuery}%");
                    })
                    ->orWhereHas('club1', function ($clubQuery) use ($searchQuery) {
                        $clubQuery->where('title', 'LIKE', "%{$searchQuery}%")
                            ->orWhereHas('city', function ($cityQuery) use ($searchQuery) {
                                $cityQuery->where('title', 'LIKE', "%{$searchQuery}%");
                            });
                    })
                    ->orWhereHas('club2', function ($clubQuery) use ($searchQuery) {
                        $clubQuery->where('title', 'LIKE', "%{$searchQuery}%")
                            ->orWhereHas('city', function ($cityQuery) use ($searchQuery) {
                                $cityQuery->where('title', 'LIKE', "%{$searchQuery}%");
                            });
                    });
            });
        }

        // Применяем сортировку

        $sortField = $request->input('sort_field', 'date_from'); // Поле для сортировки по умолчанию - date_from
        $sortDirection = $request->input('sort_direction', 'asc'); // Направление сортировки по умолчанию - asc

        // Обработка нового параметра sort
        if ($sort) {
            if ($sort === 'date_from_asc') {
                $sortField = 'date_from';
                $sortDirection = 'asc';
            } elseif ($sort === 'date_from_desc') {
                $sortField = 'date_from';
                $sortDirection = 'desc';
            }
        } else {
            $sortDirection = strtolower($sortDirection);
            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'asc';
            }
        }

        // Обработка специальных случаев сортировки, требующих join
        if ($sortField === 'competition_title') {
            $query->join('competitions', 'events.competition_id', '=', 'competitions.id')
                ->orderBy('competitions.title', $sortDirection)
                ->select('events.*'); // Важно сохранить выборку полей events
        } else {
            // Применяем стандартную сортировку
            $query->orderBy($sortField, $sortDirection);
        }

        // Получаем общее количество записей с учетом фильтрации
        $total = $query->count();

        // Дополнительная защита от слишком большого количества записей
        if ($total > 10000) {
            // Если записей слишком много, ограничиваем результат
            \Illuminate\Support\Facades\Log::warning("ApiEventController: Попытка получить слишком много записей ({$total}). Ограничиваем до 1000.");
            $total = 1000;
        }

        // Обработка параметра get_events
        if ($getEvents !== null) {
            // Если get_events задан, ограничиваем количество записей
            $events = $query->take($getEvents)->get();
        } else {
            // Иначе применяем пагинацию
            $events = $query->paginate($perPage, ['*'], 'page', $page);
        }

        // Логируем для отладки
        \Illuminate\Support\Facades\Log::info("ApiEventController: Получено {$total} записей, возвращается " . count($events) . " записей");

        // считаем серию
        $events->transform(function ($event) use ($regionId) {
            // Преобразуем date_from в формат "DD.MM.YYYY."
            $event->date_formatted = \Carbon\Carbon::parse($event->date_from)->format('d.m.Y.');

            // Извлекаем время из date_from в формате "HH:MM"
            $event->time = \Carbon\Carbon::parse($event->date_from)->format('H:i');

            // Добавляем параметр my_region
            $event->my_region = 1;
            if ($regionId && $event->region_id != $regionId) {
                $event->my_region = 0;
            }

            // Добавляем поле tickets
            $event->tickets = $event->tickets;
            $event->free_tickets = $event->free_tickets;

            // Добавляем parse_table_id в корень
            $event->parse_table_id = $event->competition->parse_table_id ?? null;

            // Определяем сезон по дате события и добавляем к названию соревнования
            $eventDate = \Carbon\Carbon::parse($event->date_from);
            $seasonTitle = $this->getSeasonTitleForEvent($event->competition_id, $eventDate);

            if ($seasonTitle) {
                $event->competition->title_with_season = $event->competition->title . ' | ' . $seasonTitle;
            } else {
                $event->competition->title_with_season = $event->competition->title;
            }

            // Вычисляем series_count если он не установлен
            if ($event->series_id) {
                if ($event->series_count === null) {
                    $eventModel = Event::find($event->id);
                    if ($eventModel) {
                        $this->calculateSeriesCount($eventModel);
                        $event->series_count = $eventModel->series_count;
                    }
                }
            } else {
                $event->series_count = null;
            }

            return $event;
        });

        // Формируем ответ
        $response = [
            'total' => $total,
            'per_page' => $getEvents !== null ? null : $perPage,
            'current_page' => $getEvents !== null ? null : $page,
            'data' => $events,
        ];

        return $response;
    }

    /**
     * Возвращает события на сегодня с аренами и координатами для карты
     */
    public function eventsTodayMap(Request $request)
    {
        $today = now()->toDateString();
        $events = Event::query()
            ->whereDate('date_from', $today)
            ->where('is_active', 1)
            ->with(['arena' => function ($query) {
                $query->select([
                    'id',
                    'title',
                    'address',
                    'image',
                    'latitude',
                    'longitude',
                    'slug',
                    'city_id',
                ]);
            }])
            ->get();

        // Формируем массив для карты
        $result = $events->map(function ($event) {
            return [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'arena_id' => $event->arena?->id,
                'arena_title' => $event->arena?->title,
                'arena_address' => strip_tags($event->arena?->address),
                'arena_image_path' => $event->arena?->image ? (config('app.url') . '/storage/' . $event->arena->image) : null,
                'latitude' => $event->arena?->latitude,
                'longitude' => $event->arena?->longitude,
                'date_formatted' => \Carbon\Carbon::parse($event->date_from)->format('d.m.Y.'),
                'time' => \Carbon\Carbon::parse($event->date_from)->format('H:i'),
            ];
        })->filter(function ($item) {
            // Только если есть координаты
            return $item['latitude'] && $item['longitude'];
        })->values();

        return response()->json(['data' => $result]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Обработка boolean полей перед созданием
        $input = $request->all();

        // Преобразуем значения "on" в true для boolean полей
        if (isset($input['free_tickets'])) {
            if ($input['free_tickets'] === 'on' || $input['free_tickets'] === '1' || $input['free_tickets'] === true) {
                $input['free_tickets'] = true;
            } elseif ($input['free_tickets'] === 'off' || $input['free_tickets'] === '0' || $input['free_tickets'] === false) {
                $input['free_tickets'] = false;
            }
        }

        if (isset($input['is_active'])) {
            if ($input['is_active'] === 'on' || $input['is_active'] === '1' || $input['is_active'] === true) {
                $input['is_active'] = true;
            } elseif ($input['is_active'] === 'off' || $input['is_active'] === '0' || $input['is_active'] === false) {
                $input['is_active'] = false;
            }
        }

        if (isset($input['show_numbers_club1'])) {
            if ($input['show_numbers_club1'] === 'on' || $input['show_numbers_club1'] === '1' || $input['show_numbers_club1'] === true) {
                $input['show_numbers_club1'] = true;
            } elseif ($input['show_numbers_club1'] === 'off' || $input['show_numbers_club1'] === '0' || $input['show_numbers_club1'] === false) {
                $input['show_numbers_club1'] = false;
            }
        }

        if (isset($input['show_numbers_club2'])) {
            if ($input['show_numbers_club2'] === 'on' || $input['show_numbers_club2'] === '1' || $input['show_numbers_club2'] === true) {
                $input['show_numbers_club2'] = true;
            } elseif ($input['show_numbers_club2'] === 'off' || $input['show_numbers_club2'] === '0' || $input['show_numbers_club2'] === false) {
                $input['show_numbers_club2'] = false;
            }
        }

        $event = Event::create($input);

        if ($event->series_id) {
            $this->calculateSeriesCount($event);
        }

        return response()->json($event, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event, $id): array
    {
        $event = Event::with([
            'streams',
            'series' => function ($query) {
                $query->select(['id', 'description']);
            },
            'competition' => function ($query) {
                $query->select([
                    'id', // Явно указываем таблицу
                    'title',
                    DB::raw("CONCAT('" . config('app.url') . "', '/storage/', competitions.image) AS full_image_path"),
                    'title_short',
                    'image',
                    'sport_id',
                    'gender_id',
                    'parse_table_id',
                ])->with([
                    'sport' => function ($sportQuery) {
                        $sportQuery->select(['id', 'title', 'slug', 'icon']);
                    },
                    'gender' => function ($sportQuery) {
                        $sportQuery->select(['id', 'title', 'title_short', 'icon']);
                    },
                ]);
            },
            'club1' => function ($query) {
                $query->select([
                    'clubs.id', // Явно указываем таблицу
                    'clubs.title',
                    'clubs.image',
                    DB::raw("CONCAT('" . config('app.url') . "', '/storage/', clubs.image) AS club_image_path"),
                    'clubs.slug',
                    'clubs.city_id',
                    'clubs.age_id',
                    'clubs.gender_id',
                    'clubs.sport_id' // Для HasMany!!!!
                ])->with([
                    'city' => function ($cityQuery) {
                        $cityQuery->select(['cities.id', 'cities.title', 'cities.title_short']);
                    },
                    'sport' => function ($sportQuery) {
                        $sportQuery->select(['id', 'title', 'slug', 'icon']);
                    },
                    'age' => function ($ageQuery) {
                        $ageQuery->select(['ages.id', 'ages.title', 'ages.title_short']);
                    },
                    'gender' => function ($genderQuery) {
                        $genderQuery->select(['id', 'title', 'icon']);
                    }
                ]);
            },
            'club2' => function ($query) {
                $query->select([
                    'clubs.id', // Явно указываем таблицу
                    'clubs.title',
                    'clubs.image',
                    DB::raw("CONCAT('" . config('app.url') . "', '/storage/', clubs.image) AS club_image_path"),
                    'clubs.slug',
                    'clubs.city_id',
                    'clubs.age_id',
                    'clubs.gender_id',
                    'clubs.sport_id' // Для HasMany!!!!
                ])->with([
                    'city' => function ($cityQuery) {
                        $cityQuery->select(['cities.id', 'cities.title', 'cities.title_short']);
                    },
                    'sport' => function ($sportQuery) {
                        $sportQuery->select(['id', 'title', 'slug', 'icon']);
                    },
                    'age' => function ($ageQuery) {
                        $ageQuery->select(['ages.id', 'ages.title', 'ages.title_short']);
                    },
                    'gender' => function ($genderQuery) {
                        $genderQuery->select(['id', 'title', 'icon']);
                    }
                ]);
            },
            'gallery' => function ($galleryQuery) {
                $galleryQuery->select([
                    'galleries.id',
                    'galleries.title',
                    'galleries.image_id'
                ])
                    ->with([
                        'images' => function ($q) {
                            $q->select([
                                'images.id',
                                'images.title',
                                'images.image',
                                'images.gallery_id'
                            ]);
                        },
                        'main_image' => function ($q) {
                            $q->select([
                                'images.id',
                                'images.title'
                            ]);
                        }
                    ]);
            },
            'articles' => function ($query) {
                $query->select([
                    'articles.id', // Явно указываем таблицу
                    'articles.title',
                    'articles.slug',
                    'articles.image'
                ]);
            },
            'arena' => function ($query) {
                $query->select([
                    'arenas.id', // Явно указываем таблицу
                    'arenas.title',
                    'arenas.city_id',
                    'arenas.slug',
                    'arenas.address',
                    'arenas.map',
                    'arenas.image',
                    'arenas.latitude',
                    'arenas.longitude',
                    DB::raw("CONCAT('" . config('app.url') . "', '/storage/', arenas.image) AS arena_image_path"),
                ])->with([
                    'city' => function ($cityQuery) {
                        $cityQuery->select(['id', 'title', 'title_short']);
                    }
                ]);
            },
        ])
        ->findOrFail($id);

        // Загружаем события серии отдельно
        if ($event->series_id) {
            $seriesEvents = Event::where('series_id', $event->series_id)
                ->select(['id', 'date_from', 'club1_id', 'club2_id', 'result', 'result_dop', 'is_active', 'report', 'free_tickets'])
                ->with([
                    'club1' => function ($query) {
                        $query->select(['id', 'title', 'city_id'])
                            ->with(['city' => function ($query) {
                                $query->select(['id', 'title', 'title_short']);
                            }]);
                    },
                    'club2' => function ($query) {
                        $query->select(['id', 'title', 'city_id'])
                            ->with(['city' => function ($query) {
                                $query->select(['id', 'title', 'title_short']);
                            }]);
                    }
                ])
                ->orderBy('date_from', 'asc')
                ->get();

            $event->series->events = $seriesEvents;
        }

        // Добавляем parse_table_id в корень
        $event->parse_table_id = $event->competition->parse_table_id ?? null;

        // Определяем сезон по дате события и добавляем к названию соревнования
        $eventDate = \Carbon\Carbon::parse($event->date_from);
        $seasonTitle = $this->getSeasonTitleForEvent($event->competition_id, $eventDate);

        if ($seasonTitle) {
            $event->competition->title_with_season = $event->competition->title . ' | ' . $seasonTitle;
        } else {
            $event->competition->title_with_season = $event->competition->title;
        }

        return [$event->toArray()];
    }

    /**
     * Получить похожие события
     */
    public function similar($id): array
    {
        try {
            $event = Event::findOrFail($id);

            // Находим похожие события (те же команды или тот же турнир)
            $similarEvents = Event::with(['club1', 'club2'])
                ->where('id', '!=', $id)
                ->where(function ($query) use ($event) {
                    $query->where(function ($q) use ($event) {
                        $q->where('club1_id', $event->club1_id)
                          ->where('club2_id', $event->club2_id);
                    })->orWhere(function ($q) use ($event) {
                        $q->where('club1_id', $event->club2_id)
                          ->where('club2_id', $event->club1_id);
                    })->orWhere('competition_id', $event->competition_id);
                })
                ->orderBy('date_from', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($similarEvent) {
                    return [
                        'id' => $similarEvent->id,
                        'date' => $similarEvent->date_from,
                        'home_team' => [
                            'id' => $similarEvent->club1->id,
                            'title' => $similarEvent->club1->title,
                        ],
                        'away_team' => [
                            'id' => $similarEvent->club2->id,
                            'title' => $similarEvent->club2->title,
                        ],
                        'home_score' => $this->extractScore($similarEvent->result, 'home'),
                        'away_score' => $this->extractScore($similarEvent->result, 'away'),
                    ];
                });

            return $similarEvents->toArray();

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Извлекает счет из результата события
     */
    private function extractScore($result, $team): ?int
    {
        if (!$result) {
            return null;
        }

        // Если результат в формате "2:1" или "2 - 1"
        if (is_string($result)) {
            $scores = preg_split('/[:\-\s]+/', $result);
            if (count($scores) >= 2) {
                return $team === 'home' ? (int)$scores[0] : (int)$scores[1];
            }
        }

        // Если результат в формате массива
        if (is_array($result)) {
            return $result[$team] ?? null;
        }

        return null;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        // Обработка boolean полей перед валидацией
        $input = $request->all();

        // Преобразуем значения "on" в true для boolean полей
        if (isset($input['free_tickets'])) {
            if ($input['free_tickets'] === 'on' || $input['free_tickets'] === '1' || $input['free_tickets'] === true) {
                $input['free_tickets'] = true;
            } elseif ($input['free_tickets'] === 'off' || $input['free_tickets'] === '0' || $input['free_tickets'] === false) {
                $input['free_tickets'] = false;
            }
        }

        if (isset($input['is_active'])) {
            if ($input['is_active'] === 'on' || $input['is_active'] === '1' || $input['is_active'] === true) {
                $input['is_active'] = true;
            } elseif ($input['is_active'] === 'off' || $input['is_active'] === '0' || $input['is_active'] === false) {
                $input['is_active'] = false;
            }
        }

        if (isset($input['show_numbers_club1'])) {
            if ($input['show_numbers_club1'] === 'on' || $input['show_numbers_club1'] === '1' || $input['show_numbers_club1'] === true) {
                $input['show_numbers_club1'] = true;
            } elseif ($input['show_numbers_club1'] === 'off' || $input['show_numbers_club1'] === '0' || $input['show_numbers_club1'] === false) {
                $input['show_numbers_club1'] = false;
            }
        }

        if (isset($input['show_numbers_club2'])) {
            if ($input['show_numbers_club2'] === 'on' || $input['show_numbers_club2'] === '1' || $input['show_numbers_club2'] === true) {
                $input['show_numbers_club2'] = true;
            } elseif ($input['show_numbers_club2'] === 'off' || $input['show_numbers_club2'] === '0' || $input['show_numbers_club2'] === false) {
                $input['show_numbers_club2'] = false;
            }
        }

        // Создаем новый Request с обработанными данными
        $request->merge($input);

        $validated = $request->validate([
            'show_numbers_club1' => 'nullable|boolean',
            'show_numbers_club2' => 'nullable|boolean',
            'free_tickets' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            // ... другие поля, если нужно
        ]);

        $event->update($validated);

        if ($event->series_id) {
            $this->calculateSeriesCount($event);
        }

        return response()->json(['success' => true, 'data' => $event]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gender $gender)
    {
        //
    }

    /**
     * Получить название сезона для события по дате
     */
    private function getSeasonTitleForEvent(int $competitionId, \Carbon\Carbon $eventDate): ?string
    {
        // Сначала проверяем competition_seasons
        $competitionSeason = \App\Models\CompetitionSeason::where('competition_id', $competitionId)
            ->where('is_active', true)
            ->where('date_from', '<=', $eventDate)
            ->where(function($query) use ($eventDate) {
                $query->where('date_to', '>=', $eventDate)
                      ->orWhereNull('date_to');
            })
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->first();

        if ($competitionSeason) {
            return $competitionSeason->title;
        }

        // Если не нашли в competition_seasons, проверяем общие сезоны через связь
        $season = \App\Models\Season::where('is_active', true)
            ->where('date_from', '<=', $eventDate)
            ->where(function($query) use ($eventDate) {
                $query->where('date_to', '>=', $eventDate)
                      ->orWhereNull('date_to');
            })
            ->whereHas('competitions', function($query) use ($competitionId) {
                $query->where('competitions.id', $competitionId);
            })
            ->first();

        if ($season) {
            return $season->title;
        }

        return null;
    }
}
