<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Arena;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiArenaController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request): array
    {
        $homeRegion = $request->input('home_region', 1);
        $showNative = $request->input('show_native', 1);

        $title = $request->query('title');
        $query = Arena::query()
            ->select(
                'id',
                'title',
                'address',
                'slug',
                'region_id',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                'city_id'
            )
            ->when($showNative == 1, function ($query) use ($homeRegion) {
                $query->where('arenas.region_id', $homeRegion);
            })
            ->with([
                'city' => function ($cityQuery) {
                    $cityQuery->select(['id', 'title']);
                }
            ])
            ->with([
                'sports' => function ($cityQuery) {
                    $cityQuery->select(['sports.id', 'sports.title', 'sports.title_short', 'sports.slug', 'sports.icon']);
                }
            ])
            ->with([
                'clubs' => function ($cityQuery) {
                    $cityQuery->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.city_id',
                        'clubs.slug'
                    ]);
                }
            ]);

        // Фильтрация по спорту (sport.slug)
        if ($request->has('sport') && $request->input('sport')) {
            $query->whereHas('sports', function ($sportQuery) use ($request) {
                $sportQuery->where('slug', $request->input('sport'));
            });
        }

        if ($title) {
            $query->where('title', '=', "{$title}");
        }


        // Фильтрация по команде (club.slug)
        if ($request->has('club') && $request->input('club')) {
            $query->whereHas('clubs', function ($clubQuery) use ($request) {
                $clubQuery->where('slug', $request->input('club'));
            });
        }

        // Поиск по названию (like по title)
        if ($request->has('q') && $request->input('q')) {
            $query->where('title', 'like', '%' . $request->input('q') . '%');
        }

        if ($request->input('type')) return $query->limit($request->input('limit', 10))->get()->toArray();

        // Пагинация
        $perPage = $request->input('per_page', 15); // Количество элементов на странице, по умолчанию 15
        $arenas = $query->paginate($perPage);


        // Возвращаем данные с пагинацией
        return [
            'data' => $arenas->items(),
            'pagination' => [
                'total' => $arenas->total(),
                'per_page' => $arenas->perPage(),
                'current_page' => $arenas->currentPage(),
                'last_page' => $arenas->lastPage(),
                'from' => $arenas->firstItem(),
                'to' => $arenas->lastItem(),
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
    public function show(Arena $gender, $slug, Request $request): array
    {
        $homeRegion = $request->input('home_region', 1);
        $showNative = $request->input('show_native', 1);

        return Arena::select(
            '*',
            'region_id',
            DB::raw('CONCAT("' . config('app.url') . '", "/storage/", arenas.image) AS full_image_path')
        )
            ->where('slug', $slug)
            ->when($showNative == 1, function ($query) use ($homeRegion) {
                $query->where('arenas.region_id', $homeRegion);
            })
            ->with([
                'clubs' => function ($query) use ($homeRegion, $showNative) {
                    $query->select([
                        'clubs.id', // Явно указываем таблицу
                        'clubs.title',
                        DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                        'clubs.slug',
                        'clubs.city_id',
                        'clubs.age_id',
                        'clubs.gender_id',
                        'clubs.sport_id', // Для HasMany!!!!
                        'clubs.region_id'
                    ])
                    ->when($showNative == 1, function ($query) use ($homeRegion) {
                        $query->where('clubs.region_id', $homeRegion);
                    })
                    ->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['cities.id', 'cities.title']);
                        },
                        'age' => function ($ageQuery) {
                            $ageQuery->select(['ages.id', 'ages.title_short']);
                        },
                        'gender' => function ($genderQuery) {
                            $genderQuery->select(['genders.id', 'genders.title', 'genders.icon']);
                        },
                        'sport' => function ($genderQuery) {
                            $genderQuery->select(['sports.id', 'sports.title', 'sports.icon']);
                        }
                    ]);
                },
                'city' => function ($cityQuery) {
                    $cityQuery->select(['cities.id', 'cities.title']);
                },
                'sports',
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
                                    'images.gallery_id',
                                    'images.position',
                                ]);
                            },
                            'main_image' => function ($q) {
                                $q->select([
                                    'images.id',
                                    'images.title'
                                ]);
                            }
                        ]);
                }
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
