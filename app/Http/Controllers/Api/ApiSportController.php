<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        $searchQuery = $request->query('q'); // Параметр поиска
        $perPage = $request->input('per_page', 10);
        $limit = $request->input('limit', $perPage);
        $query = Sport::query()
            ->select(
                'id',
                'title',
                'title_short',
                'annotation',
                'icon',
                'image',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                'slug',
                'vin')
            ->with([
                'sport_properties' => function ($query) {
                    $query->select([
                        'sport_properties.id', // Явно указываем таблицу
                        'sport_properties.title',
                        'sport_properties.icon'
                    ]);
                }]);

        // Применяем поиск по параметру q
        if ($searchQuery) $query->where('title', 'LIKE', "%{$searchQuery}%");
        return $query->limit($limit)->get()->toArray();
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
    public function show(Sport $gender, $id): array
    {
        return Sport::query()
            ->select(
                'id',
                'title',
                'title_short',
                'annotation',
                'icon',
                'image',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                'slug',
                'vin')
            ->where('slug', $id)
            ->with([
                'articles' => function ($query) {
                    $query->select([
                        'articles.id', // Явно указываем таблицу
                        'articles.title',
                        'articles.slug'
                    ]);
                },
                'clubs' => function ($query) {
                    $query->select([
                        'clubs.id', // Явно указываем таблицу
                        'clubs.title',
                        'full_image_path' => function ($query) {
                            $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', clubs.image) AS full_image_path"));
                        },
                        'clubs.slug',
                        'clubs.city_id',
                        'clubs.age_id',
                        'clubs.gender_id',
                        'clubs.sport_id' // Для HasMany!!!!
                    ])
                        ->where('is_alien', 0)
                        ->with([
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
                'sport_properties' => function ($query) {
                    $query->select([
                        'sport_properties.id', // Явно указываем таблицу
                        'sport_properties.title',
                        'sport_properties.icon'
                    ]);
                },
                'arenas' => function ($query) {
                    $query->select([
                        'arenas.id', // Явно указываем таблицу
                        'arenas.title',
                        'arenas.address',
                        'arenas.city_id',
                        'arenas.slug',
                        'full_image_path' => function ($query) {
                            $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', arenas.image) AS full_image_path"));
                        },
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['cities.id', 'cities.title']);
                        }
                    ])->with([
                        'sports' => function ($sportsQuery) {
                            $sportsQuery->select(['sports.id', 'sports.title', 'sports.icon']);
                        }
                    ]);
                },
                'events' => function ($query) {
                    $query->select([
                        'events.id',
                        'events.title',
                        'events.date_from',
                        'events.result',
                        'events.image',
                        'events.competition_id',
                        'events.arena_id',
                        'events.club1_id',
                        'events.club2_id',
                        DB::raw("DATE_FORMAT(events.date_from, '%d.%m.%Y.') as date_formatted"), // Форматируем дату
                        DB::raw("DATE_FORMAT(events.date_from, '%H:%i') as time") // Форматируем время])
                    ])
                        ->where('events.date_from', '>=', now()) // События, которые начнутся в будущем
                        ->orderBy('events.date_from', 'asc')     // Сортировка по дате
                        ->with(['competition' => function ($query) {
                            $query->select([
                                'id', // Явно указываем таблицу
                                'title',
                                'title_short',
                                'sport_id',
                            ])->with('sport');
                        },
                            'club1' => function ($query) {
                                $query->select([
                                    'clubs.id', // Явно указываем таблицу
                                    'clubs.title',
                                    'image' => function ($query) {
                                        $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', clubs.image) AS full_image_path"));
                                    },
                                    'clubs.slug',
                                    'clubs.city_id',
                                    'clubs.age_id',
                                    'clubs.gender_id',
                                    'clubs.sport_id' // Для HasMany!!!!
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
                                    'clubs.id', // Явно указываем таблицу
                                    'clubs.title',
                                    'image' => function ($query) {
                                        $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', clubs.image) AS full_image_path"));
                                    },
                                    'clubs.slug',
                                    'clubs.city_id',
                                    'clubs.age_id',
                                    'clubs.gender_id',
                                    'clubs.sport_id' // Для HasMany!!!!
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
                                    'id', // Явно указываем таблицу
                                    'title',
                                    'city_id',
                                    'slug',
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
                },
                'competitions' => function ($query) {
                    $query->select([
                        'id', // Явно указываем таблицу
                        'title',
                        'title_short',
                        'sport_id',
                        'gender_id',
                        DB::raw("DATE_FORMAT(competitions.date_from, '%d.%m.%Y.') as date_from_formatted"), // Форматируем дату
                        DB::raw("DATE_FORMAT(competitions.date_to, '%d.%m.%Y.') as date_to_formatted"), // Форматируем дату
                        'full_image_path' => function ($query) {
                            $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', competitions.image) AS full_image_path"));
                        },
                        'date_from',
                        'date_to',
                    ])->with([
                        'sport',
                        'gender' => function ($genderQuery) {
                            $genderQuery->select(['id', 'title', 'icon']);
                        }]);
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
