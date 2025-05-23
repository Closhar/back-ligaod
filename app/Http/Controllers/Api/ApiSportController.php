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
        $perPage = $request->input('per_page');
        $limit = $request->input('limit');
        $title = $request->query('title');
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

        if ($title) {
            $query->where('title', '=', "{$title}");
        }

        // Применяем поиск по параметру q
        if ($searchQuery) $query->where('title', 'LIKE', "%{$searchQuery}%");
        if ($perPage) $query->limit($perPage);
        if ($limit) $query->limit($limit);
        return $query->get()->toArray();
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
    public function show(Sport $gender, $id, Request $request): array
    {
        $homeRegion = $request->input('home_region', 1);
        $showNative = $request->input('show_native', 1);

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
                'clubs' => function ($query) use ($homeRegion, $showNative) {
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
                        'clubs.sport_id', // Для HasMany!!!!
                        'clubs.region_id'
                    ])
                        ->when($showNative == 1, function ($query) use ($homeRegion) {
                            $query->where('clubs.region_id', $homeRegion);
                        })
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
                'arenas' => function ($query) use ($homeRegion, $showNative) {
                    $query->select([
                        'arenas.id', // Явно указываем таблицу
                        'arenas.title',
                        'arenas.address',
                        'arenas.city_id',
                        'arenas.slug',
                        'arenas.region_id',
                        'full_image_path' => function ($query) {
                            $query->select(DB::raw("CONCAT('" . config('app.url') . "', '/storage/', arenas.image) AS full_image_path"));
                        },
                    ])
                    ->when($showNative == 1, function ($query) use ($homeRegion) {
                        $query->where('arenas.region_id', $homeRegion);
                    })
                    ->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['cities.id', 'cities.title']);
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
