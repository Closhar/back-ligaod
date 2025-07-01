<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Article;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        // Получаем параметры из запроса
        $dateFrom = $request->input('date_from', ''); // Значение по умолчанию: пустая строка
        $dateTo = $request->input('date_to', ''); // Значение по умолчанию: пустая строка
        $searchQuery = $request->input('q', ''); // Значение по умолчанию: пустая строка
        $sportSlug = $request->input('sport', ''); // Поиск по sports.slug
        $clubSlug = $request->input('club', ''); // Поиск по clubs.slug
        $competitionId = $request->input('competition_id', ''); // Поиск по competitions.id
        $arenaSlug = $request->input('arena', ''); // Поиск по arenas.slug
        $sortDirection = $request->input('sort', 'desc'); // Направление сортировки (по умолчанию asc)

        // Параметры пагинации
        $perPage = $request->input('per_page', 10); // Количество элементов на странице (по умолчанию 10)
        $page = $request->input('page', 1); // Текущая страница (по умолчанию 1)

        $limit = $request->input('limit');

        // Строим запрос
        $query = Article::query()
            ->select(
                'id',
                'title',
                'description',
                'slug',
                'data',
                'views',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                DB::raw("DATE_FORMAT(data, '%d.%m.%Y %H:%i') as date_formatted") // Форматируем дату
            )
            ->with([
                'competitions' => function ($cityQuery) {
                    $cityQuery->select([
                        'competitions.id',
                        'competitions.title',
                        'competitions.slug',
                        DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS competition_image')
                    ]);
                },
                'sports' => function ($cityQuery) {
                    $cityQuery->select(['sports.id', 'sports.title', 'sports.title_short', 'sports.slug', 'sports.icon']);
                },
                'clubs' => function ($cityQuery) {
                    $cityQuery->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.slug'
                    ]);
                }
            ]);

        // Фильтрация по дате
        if (!empty($dateFrom)) {
            if (!empty($dateTo)) {
                // Если заданы обе даты, используем диапазон с учетом времени
                $query->whereBetween('data', [
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59'
                ]);
            } else {
                // Если задана только date_from, ищем за весь день
                $query->whereBetween('data', [
                    $dateFrom . ' 00:00:00',
                    $dateFrom . ' 23:59:59'
                ]);
            }
        }

        // Поиск по тексту
        if (!empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('title', 'like', "%{$searchQuery}%")
                    ->orWhere('description', 'like', "%{$searchQuery}%")
                    ->orWhere('content', 'like', "%{$searchQuery}%");
            });
        }

        // Фильтрация по sport (sports.slug)
        if (!empty($sportSlug)) {
            $query->whereHas('sports', function ($q) use ($sportSlug) {
                $q->where('slug', $sportSlug);
            });
        }

        // Фильтрация по club (clubs.slug)
        if (!empty($clubSlug)) {
            $query->whereHas('clubs', function ($q) use ($clubSlug) {
                $q->where('slug', $clubSlug);
            });
        }

        // Фильтрация по competition_id (competitions.id)
        if (!empty($competitionId)) {
            $query->whereHas('competitions', function ($q) use ($competitionId) {
                $q->where('competitions.id', $competitionId);
            });
        }

        // Фильтрация по arena (arenas.slug)
        if (!empty($arenaSlug)) {
            $query->whereHas('arenas', function ($q) use ($arenaSlug) {
                $q->where('slug', $arenaSlug);
            });
        }

        // Добавляем сортировку по полю data
        $query->orderBy('data', $sortDirection === 'desc' ? 'desc' : 'asc');

        if ($limit) return $query->limit($limit)->get()->toArray();

        // Пагинация
        $articles = $query->paginate($perPage, ['*'], 'page', $page);

        // Возвращаем результат с пагинацией
        return [
            'data' => $articles->items(), // Данные статей
            'pagination' => [
                'total' => $articles->total(), // Общее количество статей
                'per_page' => $articles->perPage(), // Количество статей на странице
                'current_page' => $articles->currentPage(), // Текущая страница
                'last_page' => $articles->lastPage(), // Последняя страница
                'from' => $articles->firstItem(), // Номер первого элемента на странице
                'to' => $articles->lastItem(), // Номер последнего элемента на странице
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
    public function show($id): array
    {
        return Article::query()
            ->select(
                '*'
            )
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->with([
                'galleries' => function ($galleryQuery) {
                    $galleryQuery->select([
                        'galleries.id',
                        'galleries.title',
                    ])
                        ->with([
                            'images'
                        ]);
                },
                'videos',
                'clubs' => function ($query) {
                    $query->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.image',
                        DB::raw('CONCAT("' . config('app.url') . '", "/storage/", clubs.image) AS club_image_path'),
                        'clubs.slug',
                        'clubs.city_id',
                        'clubs.age_id',
                        'clubs.gender_id',
                        'clubs.sport_id'
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title']);
                        },
                        'age' => function ($ageQuery) {
                            $ageQuery->select(['id', 'title_short']);
                        },
                        'gender' => function ($genderQuery) {
                            $genderQuery->select(['id', 'title', 'icon']);
                        },
                        'sport' => function ($sportQuery) {
                            $sportQuery->select(['id', 'title', 'icon']);
                        }
                    ]);
                },
                'arenas' => function ($query) {
                    $query->select([
                        'arenas.id', // Явно указываем таблицу
                        'arenas.title',
                        'arenas.city_id',
                        'arenas.slug',
                        'arenas.address',
                        'arenas.image',
                        DB::raw('CONCAT("' . config('app.url') . '", "/storage/", arenas.image) AS arena_image_path')
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['cities.id', 'cities.title']);
                        }
                    ]);
                },
                'sports' => function ($sportQuery) {
                    $sportQuery->select(['sports.id', 'sports.title', 'sports.slug', 'sports.icon']);
                },
                'competitions' => function ($query) {
                    $query->select([
                        'competitions.id', // Явно указываем таблицу
                        'competitions.title',
                        'competitions.title_short',
                        'competitions.sport_id',
                        'competitions.slug',
                    ])->with('sport');
                },
                'events' => function ($query) {
                    $query->select(
                        'events.id',
                        'events.title',
                        'events.date_from',
                        'events.date_to',
                        'events.result',
                        'events.competition_id',
                        'events.arena_id',
                        'events.club1_id',
                        'events.club2_id',
                        'event_name'
                    );
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
