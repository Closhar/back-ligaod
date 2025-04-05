<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Arena;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArenaController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        $query = Arena::query()
            ->select(
                'id',
                'title',
                'address',
                'slug',
                'image',
                'city_id',
                DB::raw('CONCAT("' . config('app.url') . '", "/storage/", image) AS full_image_path')
            )
            ->with([
                'city' => function ($query) {
                    $query->select(['id', 'title']);
                },
                'sports' => function ($query) {
                    $query->select(['sports.id', 'title', 'title_short', 'slug', 'icon']);
                },
                'clubs' => function ($query) {
                    $query->select([
                        'clubs.id',
                        'title',
                        'city_id',
                        'slug',
                        DB::raw('CONCAT("' . config('app.url') . '", "/storage/", clubs.image) AS full_image_path')
                    ]);
                }
            ]);

        // Фильтрация по спорту
        if ($request->filled('sport')) {
            $query->whereHas('sports', function ($q) use ($request) {
                $q->where('slug', $request->input('sport'));
            });
        }

        // Фильтрация по команде
        if ($request->filled('club')) {
            $query->whereHas('clubs', function ($q) use ($request) {
                $q->where('slug', $request->input('club'));
            });
        }

        // Поиск по названию
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->input('q') . '%')
                    ->orWhere('address', 'like', '%' . $request->input('q') . '%');
            });
        }

        // Для асинхронных запросов
        if ($request->input('type') === 'async') {
            return $query->limit($request->input('limit', 10))->get()->map(function ($arena) {
                return [
                    'id' => $arena->id,
                    'title' => $arena->title,
                    'address' => $arena->address,
                    'slug' => $arena->slug,
                    'image' => $arena->image,
                    'full_image_path' => $arena->full_image_path,
                    'city_id' => $arena->city_id,
                    'city' => $arena->city,
                    'sports' => $arena->sports,
                    'clubs' => $arena->clubs
                ];
            })->toArray();
        }

        // Сортировка (добавлена поддержка сортировки)
        if ($request->filled('sort_field') && $request->filled('sort_direction')) {
            $query->orderBy($request->input('sort_field'), $request->input('sort_direction'));
        } else {
            $query->orderBy('title', 'asc');
        }

        // Пагинация
        $perPage = $request->input('per_page', 15);
        $arenas = $query->paginate($perPage);

        // Трансформация данных
        $transformedArenas = $arenas->map(function ($arena) {
            return [
                'id' => $arena->id,
                'title' => $arena->title,
                'address' => $arena->address,
                'slug' => $arena->slug,
                'image' => $arena->image,
                'full_image_path' => $arena->full_image_path,
                'city_id' => $arena->city_id,
                'city' => $arena->city,
                'sports' => $arena->sports->map(function ($sport) {
                    return [
                        'id' => $sport->id,
                        'title' => $sport->title,
                        'title_short' => $sport->title_short,
                        'slug' => $sport->slug,
                        'icon' => $sport->icon
                    ];
                }),
                'clubs' => $arena->clubs->map(function ($club) {
                    return [
                        'id' => $club->id,
                        'title' => $club->title,
                        'city_id' => $club->city_id,
                        'slug' => $club->slug,
                        'full_image_path' => $club->full_image_path
                    ];
                })
            ];
        });

        return [
            'current_page' => $arenas->currentPage(),
            'data' => $transformedArenas,
            'first_page_url' => $arenas->url(1),
            'from' => $arenas->firstItem(),
            'last_page' => $arenas->lastPage(),
            'last_page_url' => $arenas->url($arenas->lastPage()),
            'links' => $arenas->links(),
            'next_page_url' => $arenas->nextPageUrl(),
            'path' => $arenas->path(),
            'per_page' => $arenas->perPage(),
            'prev_page_url' => $arenas->previousPageUrl(),
            'to' => $arenas->lastItem(),
            'total' => $arenas->total(),
        ];
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
            ]);

            $item = Arena::create($validated);

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
            $item = Arena::findOrFail($id);
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
                'title' => 'required|string|max:255',
                // Добавьте другие поля при необходимости
            ]);

            // Затем поиск и обновление
            $item = Arena::findOrFail($id);
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
            $item = Arena::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
