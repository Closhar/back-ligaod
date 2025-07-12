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
use Illuminate\Support\Facades\Log;

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
        $isAlien = $request->query('is_alien');
        $regionId = $request->query('region_id', 1);
        $perPage = $request->query('per_page', 10); // Количество элементов на странице (по умолчанию 10)
        $page = $request->query('page', 1); // Номер страницы (по умолчанию 1)
        $searchQuery = $request->query('q'); // Параметр поиска
        $limit = $request->query('limit', $perPage); // Параметр поиска
        $title = $request->query('title');
        $city = $request->query('city');

        // параметры для асинхронного поиска
        $type = $request->query('type'); // если =='async', возвращаем простую структуру для async поиска

        if ($type === 'async')
            $query = Club::query()
                ->select(
                    'clubs.id',
                    'clubs.title',
                    'clubs.slug',
                    DB::raw('CASE WHEN clubs.image IS NOT NULL AND clubs.image != "" THEN CONCAT("' . config('app.url') . '", "/storage/", clubs.image) ELSE NULL END AS full_image_path'),
                    'city_id',
                    'sport_id',
                    'gender_id',
                    'age_id',
                    'region_id',
                    DB::raw('CASE WHEN region_id = ' . $regionId . ' THEN 0 ELSE 1 END as alien'),
                    DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short) AS club_info'),
                    'clubs.tlgs_to_parse'
                )
                ->join('sports as sport', 'clubs.sport_id', '=', 'sport.id')
                ->join('cities as city', 'clubs.city_id', '=', 'city.id')
                ->join('genders as gender', 'clubs.gender_id', '=', 'gender.id');

        else
            // Создаем базовый запрос
            $query = Club::query()
                ->select(
                    'clubs.id',
                    'clubs.title',
                    'clubs.slug',
                    DB::raw('CASE WHEN clubs.image IS NOT NULL AND clubs.image != "" THEN CONCAT("' . config('app.url') . '", "/storage/", clubs.image) ELSE NULL END AS full_image_path'),
                    'clubs.city_id',
                    'clubs.sport_id',
                    'clubs.gender_id',
                    'clubs.age_id',
                    'clubs.region_id',
                    DB::raw('CASE WHEN region_id = ' . $regionId . ' THEN 0 ELSE 1 END as alien'),
                    'clubs.tlgs_to_parse'
                )
                ->with([
                    'city' => function ($cityQuery) {
                        $cityQuery->select(['id', 'title', 'title_short']);
                    },
                    'sport' => function ($sportQuery) {
                        $sportQuery->select(['id', 'title', 'title_short', 'icon']);
                    },
                    'age' => function ($ageQuery) {
                        $ageQuery->select(['id', 'title_short']);
                    },
                    'gender' => function ($genderQuery) {
                        $genderQuery->select(['id', 'title', 'title_short', 'icon']);
                    },
                    'region' => function ($regionQuery) {
                        $regionQuery->select(['id', 'title', 'title_short']);
                    }
                ]);

        // Применяем фильтры
        if ($sportSlug) {
            $query->whereHas('sport', function ($q) use ($sportSlug) {
                $q->where('slug', $sportSlug);
            });
        }
        if ($sportId) {
            $query->where('sport_id', $sportId);
        }

        if ($genderId) {
            $query->where('gender_id', $genderId);
        }

        // Новая логика фильтрации по region_id
        if ($isAlien !== null) {
            if ($isAlien == 0) {
                $query->where('region_id', '=', $regionId);
            } elseif ($isAlien == 1) {
                $query->where(function($q) use ($regionId) {
                    $q->where('region_id', '!=', $regionId)
                      ->orWhereNull('region_id');
                });
            }
        }

        if ($city) {
            $query->whereHas('city', function ($q) use ($city) {
                $q->where('title', $city);
            });
        }

        if ($title) {
            $query->where('title', '=', "{$title}");
        }

        // Применяем поиск по параметру q
        if ($searchQuery && !$request->has('field')) {
            if ($type !== 'async') {
                $query->join('cities as city', 'clubs.city_id', '=', 'city.id')
                      ->join('sports as sport', 'clubs.sport_id', '=', 'sport.id')
                      ->join('genders as gender', 'clubs.gender_id', '=', 'gender.id');
            }
            $query->where(function($q) use ($searchQuery) {
                $q->where('clubs.title', 'LIKE', "%{$searchQuery}%")
                  ->orWhere(DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short)'), 'LIKE', "%{$searchQuery}%");
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
        $club = Club::where('id', $slug)->orWhere('slug', $slug)->firstOrFail();

        // Получаем активные членства (игроки этого клуба, у которых left_at = null)
        $activeMemberships = \App\Models\PersonClubMembership::with([
            'person.activeAmpluaMemberships.amplua',
            'person.mainImage',
        ])
            ->where('club_id', $club->id)
            ->whereNull('left_at')
            ->get()
            ->toArray();

        $clubArr = $club->toArray();
        $clubArr['active_memberships'] = $activeMemberships;
        return $clubArr;
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

    /**
     * Добавить регион к клубу
     */
    public function addRegion(Request $request, $clubId): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'region_id' => 'required|integer|exists:rating_regions,id'
        ]);

        try {
            $club = Club::findOrFail($clubId);

            // Отладочная информация
            Log::info('Добавление региона к клубу', [
                'club_id' => $clubId,
                'club_title' => $club->title,
                'old_rating_region_id' => $club->rating_region_id,
                'new_region_id' => $request->region_id
            ]);

            $club->update(['rating_region_id' => $request->region_id]);

            // Проверяем, что обновление прошло успешно
            $club->refresh();
            Log::info('Регион добавлен к клубу', [
                'club_id' => $clubId,
                'new_rating_region_id' => $club->rating_region_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Регион успешно добавлен к клубу',
                'data' => $club->load(['ratingRegion'])
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка при добавлении региона к клубу', [
                'club_id' => $clubId,
                'region_id' => $request->region_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при добавлении региона к клубу: ' . $e->getMessage()
            ], 500);
        }
    }
}
