<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Person;
use App\Models\Sport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PersonController extends Controller
{
    /**
     * Получить список всех персон с пагинацией и фильтрацией
     */
    public function index(Request $request): JsonResponse
    {
        $query = Person::with(['clubs', 'sports', 'mainImage', 'activeRoleMemberships.role']);

        // Поиск по имени
        if ($request->has('search')) {
            $query->searchByName($request->search);
        }

        // Фильтрация по типу персоны
        if ($request->has('person_type')) {
            if ($request->person_type === 'sportsman') {
                $query->sportsmen();
            } elseif ($request->person_type === 'non_sportsman') {
                $query->nonSportsmen();
            }
        }

        // Фильтрация по клубу
        if ($request->has('club_id')) {
            $query->whereHas('clubMemberships', function ($q) use ($request) {
                $q->where('club_id', $request->club_id);
            });
        }

        // Фильтрация по виду спорта
        if ($request->has('sport_id')) {
            $query->whereHas('sportMemberships', function ($q) use ($request) {
                $q->where('sport_id', $request->sport_id);
            });
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $people = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $people->items(),
            'pagination' => [
                'current_page' => $people->currentPage(),
                'last_page' => $people->lastPage(),
                'per_page' => $people->perPage(),
                'total' => $people->total(),
            ]
        ]);
    }

    /**
     * Получить конкретную персону с полной информацией
     */
    public function show(Person $person): JsonResponse
    {
        $person->load([
            'clubs',
            'sports',
            'images',
            'surnameChanges',
            'clubMemberships.club',
            'sportMemberships.sport',
            'roleMemberships.role'
        ]);

        return response()->json([
            'success' => true,
            'data' => $person
        ]);
    }

    /**
     * Создать новую персону
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'birth_date' => 'required|date|before:today',
            'passport_series' => 'nullable|string|size:4',
            'passport_number' => 'nullable|string|size:6',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $person = Person::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Персона успешно создана',
            'data' => $person
        ], 201);
    }

    /**
     * Обновить персону
     */
    public function update(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'birth_date' => 'sometimes|required|date|before:today',
            'passport_series' => 'nullable|string|size:4',
            'passport_number' => 'nullable|string|size:6',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $person->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Персона успешно обновлена',
            'data' => $person
        ]);
    }

    /**
     * Удалить персону
     */
    public function destroy(Person $person): JsonResponse
    {
        // Удаляем все изображения
        foreach ($person->images as $image) {
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        }

        $person->delete();

        return response()->json([
            'success' => true,
            'message' => 'Персона успешно удалена'
        ]);
    }

    /**
     * Получить статистику по персонам
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Person::count(),
            'sportsmen' => Person::sportsmen()->count(),
            'non_sportsmen' => Person::nonSportsmen()->count(),
            'with_active_role' => Person::withActiveRole()->count(),
            'with_clubs' => Person::whereHas('activeClubMemberships')->count(),
            'with_sports' => Person::whereHas('activeSportMemberships')->count(),
            'with_images' => Person::whereHas('images')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Получить список клубов для фильтрации
     */
    public function clubs(): JsonResponse
    {
        $clubs = Club::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $clubs
        ]);
    }

    /**
     * Получить список видов спорта для фильтрации
     */
    public function sports(): JsonResponse
    {
        $sports = Sport::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $sports
        ]);
    }
}
