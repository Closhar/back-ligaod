<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Club;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiClubController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        // Получаем параметры запроса
        $sportSlug = $request->query('sport');
        $sportId = $request->query('sport_id');
        $genderId = $request->query('gender_id');
        $isAlien = $request->query('is_alien', 100);
        $perPage = $request->query('per_page', 10); // Количество элементов на странице (по умолчанию 10)
        $page = $request->query('page', 1); // Номер страницы (по умолчанию 1)
        $searchQuery = $request->query('q'); // Параметр поиска
        $limit = $request->query('limit', $perPage); // Параметр поиска

        // параметры для асинхронного поиска
        $type = $request->query('type'); // если =='async', возвращаем простую структуру для async поиска

        if ($type === 'async')
            $query = Club::query()
                ->select(
                    'clubs.id',
                    'clubs.title',
                    'clubs.slug',
                    DB::raw('CONCAT("' . config('app.url') . '", "/storage/", clubs.image) AS full_image_path'),
                    'city_id',
                    'sport_id',
                    'gender_id',
                    'age_id',
                    'is_alien',
                    DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short) AS club_info')
                )
                ->join('sports as sport', 'clubs.sport_id', '=', 'sport.id')
                ->join('cities as city', 'clubs.city_id', '=', 'city.id')
                ->join('genders as gender', 'clubs.gender_id', '=', 'gender.id');

        else
            // Создаем базовый запрос
            $query = Club::query()
                ->select(
                    'id',
                    'title',
                    'slug',
                    DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                    'city_id',
                    'sport_id',
                    'gender_id',
                    'age_id',
                    'is_alien'
                )
                ->with([
                    'city' => function ($cityQuery) {
                        $cityQuery->select(['id', 'title']);
                    },
                    'sport' => function ($sportQuery) {
                        $sportQuery->select(['id', 'title', 'title_short', 'icon']);
                    },
                    'age' => function ($ageQuery) {
                        $ageQuery->select(['id', 'title_short']);
                    },
                    'gender' => function ($genderQuery) {
                        $genderQuery->select(['id', 'title', 'title_short', 'icon']);
                    }
                ]);

        // Применяем фильтры
        if ($sportSlug) {
            $query->whereHas('sport', function ($q) use ($sportSlug) {
                $q->where('slug', $sportSlug);
            });
        }
        if ($sportId) {
            $query->whereHas('sport', function ($q) use ($sportId) {
                $q->where('slug', $sportId);
            });
        }

        if ($genderId) {
            $query->where('gender_id', $genderId);
        }

        if (($isAlien == 1) or ($isAlien == 0)) {
            $query->where('is_alien', $isAlien);
        }

        // Применяем поиск по параметру q
        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('clubs.title', 'LIKE', "%{$searchQuery}%")
                    ->orWhereHas('city', function ($cityQuery) use ($searchQuery) {
                        $cityQuery->where('cities.title', 'LIKE', "%{$searchQuery}%");
                    });
            });
        }

        if ($type) return $query->limit($limit)->get()->toArray();

        // Применяем пагинацию
        $clubs = $query->paginate($perPage, ['*'], 'page', $page);

        // в случае async
        if ($type === 'async') return $clubs->items();
        // Возвращаем результат с пагинацией
        return [
            'data' => $clubs->items(),
            'pagination' => [
                'total' => $clubs->total(),
                'per_page' => $clubs->perPage(),
                'current_page' => $clubs->currentPage(),
                'last_page' => $clubs->lastPage(),
                'from' => $clubs->firstItem(),
                'to' => $clubs->lastItem(),
            ],
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
    public function show(Club $gender, $slug): array
    {
        return Club::select(
            '*',
            DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path')
        )
            ->where('id', $slug)
            ->orWhere('slug', $slug)
            ->with([
                'articles' => function ($query) {
                    $query->select([
                        'articles.id', // Явно указываем таблицу
                        'articles.title',
                        'articles.slug'
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
                'arenas' => function ($query) {
                    $query->select([
                        'arenas.id', // Явно указываем таблицу
                        'arenas.title',
                        'arenas.address',
                        'arenas.city_id',
                        'arenas.slug',
                        'arenas.image'
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title']);
                        }
                    ]);
                },
                'city' => function ($cityQuery) {
                    $cityQuery->select(['id', 'title']);
                },
                'sport' => function ($sportQuery) {
                    $sportQuery->select(['id', 'title', 'icon', 'slug']);
                },
                'age' => function ($ageQuery) {
                    $ageQuery->select(['id', 'title_short']);
                },
                'gender' => function ($genderQuery) {
                    $genderQuery->select(['id', 'title', 'icon']);
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
