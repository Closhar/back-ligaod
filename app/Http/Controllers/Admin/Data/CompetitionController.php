<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Gender;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Event;

class CompetitionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Получаем параметры фильтрации из запроса
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $getCompetitions = $request->input('get_competitions');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $sportSlug = $request->input('sport');
        $sportId = $request->input('sport_id');
        $genderId = $request->input('gender_id');
        $arenaSlug = $request->input('arena');
        $arenaId = $request->input('arena_id');
        $clubSlug = $request->input('club');
        $clubId = $request->input('club_id');
        $sort = $request->input('sort', 'date_from_asc');
        $show = $request->input('show', 4);
        $searchQuery = $request->input('q');
        $limit = $request->input('limit', $perPage);

        $sportSlugItem = $request->input('sport_item');
        $arenaSlugItem = $request->input('arena_item');
        $clubSlugItem = $request->input('club_item');

        if ($sportSlugItem) $sportSlug = $sportSlugItem;
        if ($arenaSlugItem) $arenaSlug = $arenaSlugItem;
        if ($clubSlugItem) $clubSlug = $clubSlugItem;

        $type = $request->query('type');

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
                'image',
                'bg_image',
                'about',
                'sites',
                'vks',
                'youtubes',
                'telegrams',
                'instagrams',
                'facebooks',
                'xs',
                'gallery_id',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path'),
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", bg_image) AS full_bg_image_path')
            )
            ->with([
                'gender' => function ($query) {
                    $query->select(['id', 'title','title_short', 'icon']);
                },
                'sport' => function ($query) {
                    $query->select(['id', 'title', 'icon']);
                },
                'arenas' => function ($query) {
                    $query->select(['arenas.id', 'title']);
                },
                'gallery' => function ($query) {
                    $query->select(['id', 'title']);
                }
            ]);

        $show_all = false;

        // Применяем фильтр по датам
        if ($dateFrom && $dateTo) {
            $query->where('date_from', '<=', $dateTo)
                ->where('date_to', '>=', $dateFrom);
            $show_all = true;
        } elseif ($dateFrom) {
            $query->where('date_from', '<=', $dateFrom)
                ->where('date_to', '>=', $dateFrom);
            $show_all = true;
        } elseif ($dateTo) {
            $query->where('date_from', '<=', $dateTo);
            $show_all = true;
        }

        if (!$show_all) {
            $today = now()->toDateString();
            switch ($show) {
                case 1:
                    $query->where(function ($q) use ($today) {
                        $q->where('date_from', '>', $today)
                            ->orWhere('date_to', '>=', $today);
                    });
                    break;
                case 2:
                    $query->where('date_to', '<', $today);
                    break;
                case 3:
                    $query->where('date_from', '<=', $today)
                        ->where('date_to', '>=', $today);
                    break;
                case 4:
                    break;
                default:
                    break;
            }
        }

        // Применяем фильтры
        if ($sportSlug) {
            $query->whereHas('sport', function ($q) use ($sportSlug) {
                $q->where('slug', $sportSlug);
            });
        }
        if ($sportId) {
            $query->whereHas('sport', function ($q) use ($sportId) {
                $q->where('id', $sportId);
            });
        }
        if ($arenaSlug) {
            $query->whereHas('arenas', function ($q) use ($arenaSlug) {
                $q->where('slug', $arenaSlug);
            });
        }
        if ($arenaId) {
            $query->whereHas('arenas', function ($q) use ($arenaId) {
                $q->where('id', $arenaId);
            });
        }
        if ($clubSlug) {
            $query->where(function ($q) use ($clubSlug) {
                $q->whereHas('clubs1', function ($q) use ($clubSlug) {
                    $q->where('slug', $clubSlug);
                })->orWhereHas('clubs2', function ($q) use ($clubSlug) {
                    $q->where('slug', $clubSlug);
                });
            });
        }
        if ($clubId) {
            $query->where(function ($q) use ($clubId) {
                $q->whereHas('clubs1', function ($q) use ($clubId) {
                    $q->where('id', $clubId);
                })->orWhereHas('clubs2', function ($q) use ($clubId) {
                    $q->where('id', $clubId);
                });
            });
        }
        if ($genderId) {
            $query->where('gender_id', $genderId);
        }

        // Поиск
        if ($searchQuery) {
            $query->where('title', 'LIKE', "%{$searchQuery}%")
                ->orWhere('title_short', 'LIKE', "%{$searchQuery}%");
        }

        // Сортировка
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
                $query->orderBy('date_from', 'asc');
                break;
        }

        // Для async запросов
        if ($type === 'async') {
            return $query->limit($limit)->get()->map(function ($competition) {
                return [
                    'id' => $competition->id,
                    'title' => $competition->title,
                    'title_short' => $competition->title_short,
                    'slug' => $competition->slug,
                    'sport_id' => $competition->sport_id,
                    'gender_id' => $competition->gender_id,
                    'date_from' => $competition->date_from,
                    'date_to' => $competition->date_to,
                    'image' => $competition->image,
                    'full_image_path' => $competition->full_image_path,
                    'bg_image' => $competition->bg_image,
                    'full_bg_image_path' => $competition->full_bg_image_path,
                    'about' => $competition->about,
                    'sites' => $competition->sites,
                    'vks' => $competition->vks,
                    'youtubes' => $competition->youtubes,
                    'telegrams' => $competition->telegrams,
                    'instagrams' => $competition->instagrams,
                    'facebooks' => $competition->facebooks,
                    'xs' => $competition->xs,
                    'gallery_id' => $competition->gallery_id,
                    'date_from_formatted' => \Carbon\Carbon::parse($competition->date_from)->format('d.m.Y.'),
                    'date_to_formatted' => \Carbon\Carbon::parse($competition->date_to)->format('d.m.Y.'),
                    'gender' => $competition->gender,
                    'sport' => $competition->sport,
                    'arenas' => $competition->arenas,
                    'gallery' => $competition->gallery
                ];
            })->toArray();
        }

        // Получаем данные
        if ($getCompetitions !== null) {
            $competitions = $query->take($getCompetitions)->get();
            $total = $competitions->count();
        } else {
            $competitions = $query->paginate($perPage, ['*'], 'page', $page);
            $total = $competitions->total();
        }

        // Трансформируем данные
        $transformedCompetitions = $competitions->map(function ($competition) {
            return [
                'id' => $competition->id,
                'title' => $competition->title,
                'title_short' => $competition->title_short,
                'slug' => $competition->slug,
                'sport_id' => $competition->sport_id,
                'gender_id' => $competition->gender_id,
                'date_from' => $competition->date_from,
                'date_to' => $competition->date_to,
                'image' => $competition->image,
                'full_image_path' => $competition->full_image_path,
                'bg_image' => $competition->bg_image,
                'full_bg_image_path' => $competition->full_bg_image_path,
                'about' => $competition->about,
                'sites' => $competition->sites,
                'vks' => $competition->vks,
                'youtubes' => $competition->youtubes,
                'telegrams' => $competition->telegrams,
                'instagrams' => $competition->instagrams,
                'facebooks' => $competition->facebooks,
                'xs' => $competition->xs,
                'gallery_id' => $competition->gallery_id,
                'date_from_formatted' => \Carbon\Carbon::parse($competition->date_from)->format('d.m.Y.'),
                'date_to_formatted' => \Carbon\Carbon::parse($competition->date_to)->format('d.m.Y.'),
                'gender' => $competition->gender,
                'sport' => $competition->sport,
                'arenas' => $competition->arenas,
                'gallery' => $competition->gallery
            ];
        });

        // Формируем ответ
        if ($getCompetitions !== null) {
            return $transformedCompetitions;
        } else {
            return [
                'current_page' => $competitions->currentPage(),
                'data' => $transformedCompetitions,
                'first_page_url' => $competitions->url(1),
                'from' => $competitions->firstItem(),
                'last_page' => $competitions->lastPage(),
                'last_page_url' => $competitions->url($competitions->lastPage()),
                'links' => $competitions->links(),
                'next_page_url' => $competitions->nextPageUrl(),
                'path' => $competitions->path(),
                'per_page' => $competitions->perPage(),
                'prev_page_url' => $competitions->previousPageUrl(),
                'to' => $competitions->lastItem(),
                'total' => $total,
            ];
        }
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'about' => 'nullable|string',
                'sites' => 'nullable|string',
                'title_short' => 'nullable|string',
                'slug' => 'nullable|string',
                'sport_id' => 'nullable|exists:sports,id',
                'gender_id' => 'nullable|exists:genders,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'image' => 'nullable|image',
                'bg_image' => 'nullable|image',
                'vks' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'telegrams' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'gallery_id' => 'nullable|exists:galleries,id'
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
            $item = Event::with('gallery')->findOrFail($id);
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
                'title' => 'string|max:255',
                'about' => 'nullable|string',
                'sites' => 'nullable|string',
                'title_short' => 'nullable|string',
                'slug' => 'nullable|string',
                'sport_id' => 'nullable|exists:sports,id',
                'gender_id' => 'nullable|exists:genders,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'vks' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'telegrams' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'gallery_id' => 'nullable|exists:galleries,id'
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
