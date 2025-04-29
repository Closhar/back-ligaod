<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Gender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiEventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        // Получаем параметры фильтрации из запроса
        //$perPage = $request->input('events', 10);
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
        $regionId = $request->input('region_id');
        $is_active = $request->input('is_active');
        $show_native = $request->input('show_native'); // Показывать события с командой с regionID независимо от региона события

        $sportSlugItem = $request->input('sport_item');
        $arenaSlugItem = $request->input('arena_item');
        $clubSlugItem = $request->input('club_item');

        if ($sportSlugItem) $sportSlug = $sportSlugItem;
        if ($arenaSlugItem) $arenaSlug = $arenaSlugItem;
        if ($clubSlugItem) $clubSlug = $clubSlugItem;

        // Основной запрос с фильтрацией
        $query = Event::query()
            ->select('id', 'title', 'date_from', 'date_to', 'result', 'result_dop', 'image', 'competition_id', 'arena_id', 'club1_id', 'club2_id', 'region_id', 'is_active', 'event_name')
            ->with([
                'region' => function ($query) {
                    $query->select(['id', 'title', 'title_short']);
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

        if ($regionId) {
            if ($show_native) {
                $query->where(function($q) use ($regionId) {
                    $q->where('region_id', $regionId)
                        ->orWhere(function($q2) use ($regionId) {
                            $q2->where('region_id', '!=', $regionId)
                                ->where(function($q3) use ($regionId) {
                                    $q3->whereHas('club1', function($clubQuery) use ($regionId) {
                                        $clubQuery->where('club1.region_id', $regionId);
                                    })
                                    ->orWhereHas('club2', function($clubQuery) use ($regionId) {
                                        $clubQuery->where('club2.region_id', $regionId);
                                    });
                                });
                        });
                });
            } else {
                $query->where('region_id', $regionId);
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

        if ((!$show_concrete_date) && ($show)) {
            // Применяем фильтр по show
            $today = now()->toDateString(); // Сегодняшняя дата в формате 'Y-m-d'
            switch ($show) {
                case 1: // date_from >= сегодня
                    $query->whereDate('date_from', '>=', $today);
                    break;
                case 2: // date_from <= сегодня ИЛИ date_to >= сегодня (события, которые начались до сегодня и еще продолжаются)
                    {
                        $query->where(function ($q) use ($today) {
                            $q->whereDate('date_from', '<=', $today);
                        });
                    }
                    break;
                case 3: // date_from = сегодня
                    $query->whereDate('date_from', '=', $today); // Фильтр по сегодняшней дате, игнорируя время
                    break;
                case 4: // Без ограничений по date_from
                    break;
                default:
                    $query->whereDate('date_from', '>=', $today); // По умолчанию
                    break;
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

        $sortDirection = strtolower($sortDirection);
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
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

        // Обработка параметра get_events
        if ($getEvents !== null) {
            // Если get_events задан, ограничиваем количество записей
            $events = $query->take($getEvents)->get();
        } else {
            // Иначе применяем пагинацию
            $events = $query->paginate($perPage, ['*'], 'page', $page);
        }

        // преобразуем дату и время
        $events->transform(function ($event) {
            // Преобразуем date_from в формат "DD.MM.YYYY."
            $event->date_formatted = \Carbon\Carbon::parse($event->date_from)->format('d.m.Y.');

            // Извлекаем время из date_from в формате "HH:MM"
            $event->time = \Carbon\Carbon::parse($event->date_from)->format('H:i');

            return $event;
        });

        // Формируем ответ
        return [
            'total' => $total,
            'per_page' => $getEvents !== null ? null : $perPage,
            'current_page' => $getEvents !== null ? null : $page,
            'data' => $events,
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event, $id): array
    {
        return Event::where('id', $id)
            ->with([
                'competition' => function ($query) {
                    $query->select([
                        'id', // Явно указываем таблицу
                        'title',
                        DB::raw("CONCAT('" . config('app.url') . "', '/storage/', competitions.image) AS full_image_path"),
                        'title_short',
                        'image',
                        'sport_id',
                        'gender_id',
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
                        DB::raw("CONCAT('" . config('app.url') . "', '/storage/', arenas.image) AS arena_image_path"),
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title', 'title_short']);
                        }
                    ]);
                },
            ])
            ->get()
            ->toArray();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Gender $gender)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gender $gender)
    {
        //
    }
}
