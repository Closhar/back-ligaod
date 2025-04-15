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
            $perPage = $request->query('per_page', 10); // Количество элементов на странице (по умолчанию 10)
            $limit = $request->input('limit', $perPage);

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
                    DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short) AS club_info'),
                    DB::raw('CONCAT("' . config('app.url') . '", "/storage/", clubs.image) AS full_image_path')
                ])
                ->join('sports as sport', 'clubs.sport_id', '=', 'sport.id')
                ->join('cities as city', 'clubs.city_id', '=', 'city.id')
                ->join('genders as gender', 'clubs.gender_id', '=', 'gender.id')
            ->with('city')
                ->with('gender')
                ->with('sport');


            if ($searchQuery) {
                $query->where('clubs.title', 'LIKE', "%{$searchQuery}%");
            }

            // Дополнительные фильтры (остаются без изменений)
            if ($request->has('sport')) {
                $query->whereHas('sport', fn($q) => $q->where('slug', $request->input('sport')));
            }

            if ($request->has('gender_id')) {
                $query->where('clubs.gender_id', $request->input('gender_id'));
            }

            if ($request->has('sport_id')) {
                $query->where('clubs.sport_id', $request->input('sport_id'));
            }

            if ($request->has('city_id')) {
                $query->where('clubs.city_id', $request->input('city_id'));
            }
            // ... остальные фильтры

            // Если запрошен простой вывод
            if ($type) {
                return $query->limit($limit)->get()->toArray();
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
