<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Gender;
use DB;
use Illuminate\Http\Request;

class ApiCompetitionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array
    {
        // Получаем параметры фильтрации из запроса
        $perPage = $request->input('per_page', 10); // Количество записей на странице
        $page = $request->input('page', 1); // Текущая страница
        $getCompetitions = $request->input('get_competitions'); // Количество записей для вывода без пагинации
        $dateFrom = $request->input('date_from'); // Фильтр по дате от
        $dateTo = $request->input('date_to'); // Фильтр по дате до
        $sportSlug = $request->input('sport'); // Фильтр по виду спорта
        $sportId = $request->input('sport_id'); // Фильтр по виду спорта
        $genderId = $request->input('gender_id'); // Фильтр по полу
        $arenaSlug = $request->input('arena'); // Фильтр по арене (slug)
        $arenaId = $request->input('arena_id'); // Фильтр по арене (slug)
        $clubSlug = $request->input('club'); // Фильтр по клубу (slug)
        $clubId = $request->input('club_id'); // Фильтр по клубу (slug)
        $sort = $request->input('sort', 'date_from_asc'); // Сортировка по умолчанию
        $show = $request->input('show', 4); // Временной промежуток по умолчанию
        $searchQuery = $request->input('q'); // Параметр поиска
        $limit = $request->input('limit', $perPage);

        $sportSlugItem = $request->input('sport_item');
        $arenaSlugItem = $request->input('arena_item');
        $clubSlugItem = $request->input('club_item');

        if ($sportSlugItem) $sportSlug = $sportSlugItem;
        if ($arenaSlugItem) $arenaSlug = $arenaSlugItem;
        if ($clubSlugItem) $clubSlug = $clubSlugItem;

        $type = $request->query('type'); // если =='async', возвращаем простую структуру для async поиска

        // Основной запрос с фильтрацией
        $query = Competition::query()
            ->select(
                'id',
                'title',
                'title_short',
                'slug',
                'sport_id',
                'gender_id',
                'date_from',
                'date_to',
                'parse_table_id',
                'tlgs_to_parse',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path')
            )
            ->with([
                'gender',
                'sport' => function ($query) {
                    $query->select(['id', 'title', 'icon']);
                },
                'arenas' => function ($query) {
                    $query->select(['arenas.id', 'title']);
                },
                'parseTable' => function ($query) {
                    $query->select(['id', 'title']);
                }
            ]);

        $show_all = false;

        // Применяем фильтр по date_from и date_to
        if ($dateFrom && $dateTo) {
            // Если заданы и date_from, и date_to, фильтруем по диапазону
            $query->where('date_from', '<=', $dateTo)
                ->where('date_to', '>=', $dateFrom);
            $show_all = true;
        } elseif ($dateFrom) {
            // Если задан только date_from, фильтруем по точному совпадению date_from
            $query->where('date_from', '<=', $dateFrom)
                ->where('date_to', '>=', $dateFrom);
            $show_all = true;
        } elseif ($dateTo) {
            // Если задан только date_to, фильтруем по date_from <= date_to
            $query->where('date_from', '<=', $dateTo);
            $show_all = true;
        }

        if (!$show_all) {
            // Применяем фильтр по show
            // Используем UTC время для определения сегодняшней даты
            // чтобы избежать проблем с временными зонами
            $today = \Carbon\Carbon::now('UTC')->toDateString();
            switch ($show) {
                case 1: // date_from >= актуальные: сегодня и будущие
                    $query->where(function ($q) use ($today) {
                        $q->where('date_from', '>', $today)
                            ->orWhere('date_to', '>=', $today);
                    });
                    break;
                case 2: // date_from <= сегодня ПРОШЕДШИЕ
                    $query->where('date_to', '<', $today);
                    break;
                case 3: // date_from = сегодня
                    $query->where('date_from', '<=', $today)
                        ->where('date_to', '>=', $today);
                    break;
                case 4: // Без ограничений по date_from
                    break;
                default:
                    break;
            }
        }

        // Применяем фильтр по виду спорта
        if ($sportSlug) {
            $query->whereHas('sport', function ($q) use ($sportSlug) {
                $q->where('sports.slug', $sportSlug);
            });
        }

        // Применяем фильтр по виду спорта
        if ($sportId) {
            $query->whereHas('sport', function ($q) use ($sportId) {
                $q->where('sports.id', $sportId);
            });
        }

        // Применяем фильтр по арене
        if ($arenaSlug) {
            $query->whereHas('arenas', function ($q) use ($arenaSlug) {
                $q->where('arenas.slug', $arenaSlug); // Фильтр по slug арены
            });
        }

        // Применяем фильтр по арене
        if ($arenaId) {
            $query->whereHas('arenas', function ($q) use ($arenaId) {
                $q->where('arenas.id', $arenaId); // Фильтр по slug арены
            });
        }

        // Применяем фильтр по клубу
        if ($clubSlug) {
            $query->where(function ($q) use ($clubSlug) {
                $q->whereHas('clubs1', function ($q) use ($clubSlug) {
                    $q->where('clubs.slug', $clubSlug); // Фильтр по slug клуба (club1)
                })
                    ->orWhereHas('clubs2', function ($q) use ($clubSlug) {
                        $q->where('clubs.slug', $clubSlug); // Фильтр по slug клуба (club2)
                    });
            });
        }

        // Применяем фильтр по клубу
        if ($clubId) {
            $query->whereHas('club1', function ($q) use ($clubId) {
                $q->where('clubs.id', $clubId); // Фильтр по slug клуба (club1)
            })
                ->orWhereHas('club2', function ($q) use ($clubId) {
                    $q->where('clubs.id', $clubId); // Фильтр по slug клуба (club1)
                });
        }

        // Применяем фильтр по полу
        if ($genderId) {
            $query->where('gender_id', $genderId);
        }

        // Применяем поиск по параметру q
        if ($searchQuery) {
            $query->where('title', 'LIKE', "%{$searchQuery}%")
                ->orWhere('title_short', 'LIKE', "%{$searchQuery}%");
        }

        // Применяем сортировку
        switch ($sort) {
            case 'date_from_asc':
                $query->orderBy('date_from', 'asc');
                break;
            case 'date_from_desc':
                $query->orderBy('date_from', 'desc');
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('title', 'desc');
                break;
            default:
                $query->orderBy('date_from', 'asc'); // По умолчанию сортировка по date_from по возрастанию
                break;
        }

        if ($type === 'async') return $query->limit($limit)->get()->toArray();

        // Обработка параметра get_competitions
        if ($getCompetitions !== null) {
            // Если get_competitions задан, ограничиваем количество записей
            $competitions = $query->take($getCompetitions)->get();
        } else {
            // Иначе применяем пагинацию
            $competitions = $query->paginate($perPage, ['*'], 'page', $page);
        }

        // Форматируем даты и добавляем дополнительные поля
        $competitions->transform(function ($competition) {
            // Преобразуем date_from в формат "DD.MM.YYYY."
            $competition->date_from_formatted = \Carbon\Carbon::parse($competition->date_from)->format('d.m.Y.');

            // Преобразуем date_to в формат "DD.MM.YYYY."
            $competition->date_to_formatted = \Carbon\Carbon::parse($competition->date_to)->format('d.m.Y.');

            return $competition;
        });


        // Формируем ответ с пагинацией
        if ($getCompetitions !== null) {
            // Если get_competitions задан, возвращаем данные без пагинации
            return [
                'data' => $competitions,
                'pagination' => null, // Пагинация отсутствует
            ];
        } else {
            // Возвращаем данные с пагинацией
            return [
                'data' => $competitions->items(),
                'pagination' => [
                    'total' => $competitions->total(),
                    'per_page' => $competitions->perPage(),
                    'current_page' => $competitions->currentPage(),
                    'last_page' => $competitions->lastPage(),
                    'from' => $competitions->firstItem(),
                    'to' => $competitions->lastItem(),
                ],
            ];
        }
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
        return Competition::query()
            ->select([
                '*'
            ])
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->with([
                'arenas' => function ($query) {
                    $query->select([
                        'arenas.id', // Явно указываем таблицу
                        'arenas.title',
                        'arenas.city_id',
                        'arenas.slug',
                        'arenas.image'
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title']);
                        }
                    ]);
                },
                'articles' => function ($query) {
                    $query->select([
                        'articles.id', // Явно указываем таблицу
                        'articles.title',
                        'articles.slug'
                    ]);
                },
                'sport' => function ($cityQuery) {
                    $cityQuery->select(['sports.id', 'sports.title', 'sports.icon', 'sports.slug']);
                },
                'gender' => function ($genderQuery) {
                    $genderQuery->select(['genders.id', 'genders.title', 'genders.icon']);
                },
                'parseTable' => function ($query) {
                    $query->select(['id', 'title']);
                },
                'gallery' => function ($galleryQuery) {
                    $galleryQuery->select(['galleries.id', 'galleries.title'])->with([
                        'images'
                    ]);
                },
                'clubs1' => function ($clubsQuery) {
                    $clubsQuery->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.city_id',
                        'clubs.gender_id',
                        'clubs.sport_id',
                        'clubs.slug',
                        'clubs.image'
                    ])
                        ->with(['city', 'gender', 'sport']);
                },
                'clubs2' => function ($clubsQuery) {
                    $clubsQuery->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.city_id',
                        'clubs.gender_id',
                        'clubs.sport_id',
                        'clubs.slug',
                        'clubs.image'
                    ])
                        ->with(['city', 'gender', 'sport']);
                }
            ])
            ->get()
            ->map(function ($competition) {
                // Объединяем клубы из clubs1 и clubs2 и удаляем дубликаты
                $clubs = $competition->clubs1->merge($competition->clubs2)->unique('id');
                $competition->clubs = $clubs;
                unset($competition->clubs1);
                unset($competition->clubs2);
                return $competition;
            })
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
