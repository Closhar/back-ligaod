<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Club;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Базовые параметры
            $perPage = $request->input('per_page', 10);
            $searchQuery = $request->input('q');
            $type = $request->input('type');

            // Основной запрос
            $query = Club::query()
                ->select([
                    'clubs.id',
                    'clubs.title',
                    'clubs.slug',
                    'clubs.city_id',
                    'clubs.sport_id',
                    'clubs.gender_id',
                    'clubs.age_id',
                    'clubs.is_alien',
                    'clubs.image',
                    DB::raw('CONCAT("' . config('app.url') . '", "/storage/", clubs.image) AS full_image_path')
                ])
                ->with([
                    'city' => fn($q) => $q->select(['id', 'title', 'title_short']),
                    'sport' => fn($q) => $q->select(['id', 'title', 'title_short', 'slug', 'icon']),
                    'age' => fn($q) => $q->select(['id', 'title', 'title_short']),
                    'gender' => fn($q) => $q->select(['id', 'title', 'title_short', 'icon'])
                ]);

            // Улучшенный поиск по нескольким словам
            if ($searchQuery) {
                    $query->where(function ($q) {
                                $q->where('clubs.title', 'LIKE', "%{$q}%")
                                    ->orWhereHas('city', 'LIKE', "%{$q}%");
                            });
            }

            // Дополнительные фильтры (остаются без изменений)
            if ($request->has('sport')) {
                $query->whereHas('sport', fn($q) => $q->where('slug', $request->input('sport')));
            }
            // ... остальные фильтры

            // Если запрошен простой вывод
            if ($type) {
                return Club::query()
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
                    ->join('genders as gender', 'clubs.gender_id', '=', 'gender.id')
                    ->limit(10)->get()->toArray();
            }

            // Стандартный вывод с пагинацией (как в предыдущей версии)
            $clubs = $query->paginate($perPage);

            return [
                'data' => $clubs->items(),
                'pagination' => [
                    'total' => $clubs->total(),
                    'per_page' => $clubs->perPage(),
                    'current_page' => $clubs->currentPage(),
                    'last_page' => $clubs->lastPage()
                ]
            ];

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
            ]);

            $item = Club::create($validated);

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
            $item = Club::findOrFail($id);
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
            $item = Club::findOrFail($id);
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
            $item = Club::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
