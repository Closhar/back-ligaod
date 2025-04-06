<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Sport;
use Illuminate\Http\Request;
use App\Models\AdminPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
//    public function __construct()
//    {
//        $this->middleware('auth:sanctum');
//    }

    public function index(Request $request)
    {
        // Получаем параметры фильтрации из запроса
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
        $sort = $request->input('sort', 'date_from_asc');
        $show = $request->input('show');
        $empty_result = $request->input('empty_result', false);
        $empty_time = $request->input('empty_time', false);
        $searchQuery = $request->input('q');
        $show_concrete_date = false;
        // Сортировка
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');


        $sportSlugItem = $request->input('sport_item');
        $arenaSlugItem = $request->input('arena_item');
        $clubSlugItem = $request->input('club_item');

        if ($sportSlugItem) $sportSlug = $sportSlugItem;
        if ($arenaSlugItem) $arenaSlug = $arenaSlugItem;
        if ($clubSlugItem) $clubSlug = $clubSlugItem;

        // Основной запрос с фильтрацией
        $query = Event::query()
            ->select('id', 'title', 'date_from', 'date_to', 'result', 'image', 'competition_id', 'arena_id',
                'club1_id', 'club2_id', 'event_name')
            ->with([
                'competition' => function ($query) {
                    $query->select([
                        'id',
                        'title',
                        'title_short',
                        'image',
                        'bg_image',
                        'sport_id',
                        'gender_id',
                    ])->with(['sport:id,title,title_short,icon', 'gender']);
                },
                'club1' => function ($query) {
                    $query->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.image',
                        'clubs.slug',
                        'clubs.city_id',
                        'clubs.age_id',
                        'clubs.gender_id',
                        'clubs.sport_id',
                    ])->with([
                        'sport' => function ($q) {
                            $q->select('id', 'title_short');
                        },
                        'city' => function ($cityQuery) {
                            $cityQuery->select('id', 'title', 'title_short');
                        },
                        'age' => function ($ageQuery) {
                            $ageQuery->select('id', 'title_short');
                        },
                        'gender' => function ($genderQuery) {
                            $genderQuery->select('id', 'title_short');
                        }
                    ]);
                },
                'club2' => function ($query) {
                    $query->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.image',
                        'clubs.slug',
                        'clubs.city_id',
                        'clubs.age_id',
                        'clubs.gender_id',
                        'clubs.sport_id',
                    ])->with([
                        'sport' => function ($q) {
                            $q->select('id', 'title_short');
                        },
                        'city' => function ($cityQuery) {
                            $cityQuery->select('id', 'title', 'title_short');
                        },
                        'age' => function ($ageQuery) {
                            $ageQuery->select('id', 'title_short');
                        },
                        'gender' => function ($genderQuery) {
                            $genderQuery->select('id', 'title_short');
                        }
                    ]);
                },
                'arena' => function ($query) {
                    $query->select([
                        'id',
                        'title',
                        'city_id',
                        'slug',
                        'image',
                    ])->with([
                        'city' => function ($cityQuery) {
                            $cityQuery->select(['id', 'title']);
                        }
                    ]);
                },
            ]);

        // Применяем фильтр по date_from и date_to
        if ($dateFrom && $dateTo) {
            $query->whereDate('date_from', '>=', $dateFrom)
                ->whereDate('date_from', '<=', $dateTo);
        } elseif ($dateFrom) {
            $query->whereDate('date_from', '=', $dateFrom);
            $show_concrete_date = true;
        } elseif ($dateTo) {
            $query->whereDate('date_from', '<=', $dateTo);
        }

        if ((!$show_concrete_date) && ($show)) {
            $today = now()->toDateString();
            switch ($show) {
                case 1:
                    $query->whereDate('date_from', '>=', $today);
                    break;
                case 2:
                    $query->where(function ($q) use ($today) {
                        $q->whereDate('date_from', '<=', $today);
                    });
                    $sort = "date_from_desc";
                    break;
                case 3:
                    $query->whereDate('date_from', '=', $today);
                    break;
                case 4:
                    break;
                default:
                    $query->whereDate('date_from', '>=', $today);
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
                $q->where('gender_id', $genderId);
            });
        }

        if ($empty_result) {
            $query->where(function ($q) {
                $q->where('result', '=', '')
                    ->orWhereNull('result');
            });
        }

        if ($empty_time) {
            $query->whereTime('date_from', '=', '00:00:00');
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

        $sortDirection = strtolower($sortDirection);
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query->orderBy($sortField, $sortDirection);

        // Получаем данные в зависимости от параметра get_events
        if ($getEvents !== null) {
            $events = $query->take($getEvents)->get();
            $total = $events->count();
        } else {
            $events = $query->paginate($perPage, ['*'], 'page', $page);
            $total = $events->total();
        }

        // Преобразуем данные для ответа
        $transformedEvents = $events->map(function ($event) {
            // Формируем club_info для club1
            $club1Info = null;
            if ($event->club1) {
                $club1Info = sprintf('%s (%s) | %s | %s',
                    $event->club1->title,
                    $event->club1->city->title_short ?? '',
                    $event->club1->sport->title_short ?? '',
                    $event->club1->gender->title_short ?? ''
                );
            }

            // Формируем club_info для club2
            $club2Info = null;
            if ($event->club2) {
                $club2Info = sprintf('%s (%s) | %s | %s',
                    $event->club2->title,
                    $event->club2->city->title_short ?? '',
                    $event->club2->sport->title_short ?? '',
                    $event->club2->gender->title_short ?? ''
                );
            }

            // Формируем URL изображений
            $club1Image = $event->club1 && $event->club1->image
                ? config('app.url') . '/storage/' . $event->club1->image
                : null;

            $club2Image = $event->club2 && $event->club2->image
                ? config('app.url') . '/storage/' . $event->club2->image
                : null;

            $arenaImage = $event->arena && $event->arena->image
                ? config('app.url') . '/storage/' . $event->arena->image
                : null;

            return [
                'id' => $event->id,
                'title' => $event->title,
                'date_from' => $event->date_from,
                'date_to' => $event->date_to,
                'result' => $event->result,
                'image' => $event->image,
                'competition_id' => $event->competition_id,
                'arena_id' => $event->arena_id,
                'club1_id' => $event->club1_id,
                'club2_id' => $event->club2_id,
                'event_name' => $event->event_name,
                'sport_icon' => $event->competition->sport->icon,
                'gender_icon' => $event->competition->gender->icon,
                'date_formatted' => \Carbon\Carbon::parse($event->date_from)->format('d.m.Y.'),
                'time' => \Carbon\Carbon::parse($event->date_from)->format('H:i'),
                'competition' => $event->competition,
                'club1' => $event->club1 ? array_merge($event->club1->toArray(), [
                    'club_info' => $club1Info,
                    'image' => $club1Image
                ]) : null,
                'club2' => $event->club2 ? array_merge($event->club2->toArray(), [
                    'club_info' => $club2Info,
                    'image' => $club2Image
                ]) : null,
                'arena' => $event->arena ? array_merge($event->arena->toArray(), [
                    'image' => $arenaImage
                ]) : null,
            ];
        });

        // Формируем ответ в формате, ожидаемом KirhTable
        if ($getEvents !== null) {
            return $transformedEvents;
        } else {
            return [
                'current_page' => $events->currentPage(),
                'data' => $transformedEvents,
                'first_page_url' => $events->url(1),
                'from' => $events->firstItem(),
                'last_page' => $events->lastPage(),
                'last_page_url' => $events->url($events->lastPage()),
                'links' => $events->links(),
                'next_page_url' => $events->nextPageUrl(),
                'path' => $events->path(),
                'per_page' => $events->perPage(),
                'prev_page_url' => $events->previousPageUrl(),
                'to' => $events->lastItem(),
                'total' => $total,
            ];
        }
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
            ]);

            $item = Event::create($validated);

            return response()->json($item, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function show($id)
    {
        try {
            $item = Event::findOrFail($id);
            return response()->json($item);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Сначала валидация
            $validated = $request->validate([
                'title' => 'string|max:255|nullable',
                'date_from' => 'datetime|nullable',
                'arena_id' => 'integer|exists:arenas,id',
                // Добавьте другие поля при необходимости
            ]);

            // Затем поиск и обновление
            $item = Event::findOrFail($id);
            $item->update($validated);

            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $item = Event::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
