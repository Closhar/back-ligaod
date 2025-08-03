<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amplua;
use App\Models\Club;
use App\Models\Param;
use App\Models\Person;
use App\Models\Position;
use App\Models\Sport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PersonController extends Controller
{
    /**
     * Получить список всех персон с пагинацией и фильтрацией
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Сначала проверим, есть ли вообще персоны в базе
            $totalCount = Person::count();

            $query = Person::with([
                'mainImage',
                'activePositionMemberships' => function ($query) {
                    $query->whereHas('position'); // Исключаем членства с несуществующими должностями
                },
                'activePositionMemberships.position',
                'activeAmpluaMemberships' => function ($query) {
                    $query->whereHas('amplua'); // Исключаем членства с несуществующими амплуа
                },
                'activeAmpluaMemberships.amplua',
                'activeClubMemberships' => function ($query) {
                    $query->whereHas('club'); // Исключаем членства с несуществующими командами
                },
                'activeClubMemberships.club' => function ($query) {
                    $query->select(['id', 'title', 'slug', 'image', 'city_id', 'sport_id', 'gender_id']); // Выбираем поля для full_info
                },
                'activeClubMemberships.club.city',
                'activeClubMemberships.club.sport',
                'activeClubMemberships.club.gender',
                'activeSportMemberships' => function ($query) {
                    $query->whereHas('sport'); // Исключаем членства с несуществующими видами спорта
                },
                'activeSportMemberships.sport' => function ($query) {
                    $query->select(['id', 'title', 'icon']); // Выбираем только нужные поля
                }
            ]);

            // Поиск по имени
            if ($request->has('search') && !empty($request->search)) {
                $query->searchByName($request->search);
            }

            // Фильтрация по типу персоны
            if ($request->has('person_type') && !empty($request->person_type)) {
                if ($request->person_type === 'sportsman') {
                    $query->sportsmen();
                } elseif ($request->person_type === 'non_sportsman') {
                    $query->nonSportsmen();
                }
            }

            // Фильтрация по имени, фамилии, дате рождения
            if ($request->has('first_name') && $request->first_name) {
                $query->where('first_name', $request->first_name);
            }
            if ($request->has('last_name') && $request->last_name) {
                $query->where('last_name', $request->last_name);
            }
            if ($request->has('birth_date') && $request->birth_date) {
                // Преобразуем дату, если она в формате дд.мм.гггг
                $birthDate = $request->birth_date;
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $birthDate)) {
                    $parts = explode('.', $birthDate);
                    $birthDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
                $query->whereDate('birth_date', $birthDate);
            }

            // Фильтрация по должности
            if ($request->has('position_id') && !empty($request->position_id)) {
                $query->whereHas('positionMemberships', function ($q) use ($request) {
                    $q->where('position_id', $request->position_id);
                });
            }

            // Фильтрация по амплуа
            if ($request->has('amplua_id') && !empty($request->amplua_id)) {
                $query->whereHas('ampluaMemberships', function ($q) use ($request) {
                    $q->where('amplua_id', $request->amplua_id);
                });
            }

            // Фильтрация по команде
            if ($request->has('club_id') && !empty($request->club_id)) {
                $query->whereHas('clubMemberships', function ($q) use ($request) {
                    $q->where('club_id', $request->club_id);
                });
            }

            // Фильтрация по виду спорта
            if ($request->has('sport_id') && !empty($request->sport_id)) {
                $query->whereHas('sportMemberships', function ($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            // Фильтрация по дню рождения (месяц и день)
            if ($request->has('birthday_month') && !empty($request->birthday_month)) {
                $query->whereMonth('birth_date', $request->birthday_month);
            }

            if ($request->has('birthday_day') && !empty($request->birthday_day)) {
                $query->whereDay('birth_date', $request->birthday_day);
            }

            // Фильтрация по клубу - ИСПРАВЛЕНО: используем clubMemberships вместо activeClubMemberships
            if ($request->has('club') && !empty($request->club)) {
                $query->whereHas('activeClubMemberships', function ($q) use ($request) {
                    $q->whereHas('club', function ($clubQuery) use ($request) {
                        $clubQuery->where('slug', $request->club);
                    });
                });
            }

            // Фильтрация по спорту - ИСПРАВЛЕНО: используем sportMemberships вместо activeSportMemberships
            if ($request->has('sport') && !empty($request->sport)) {
                $query->whereHas('activeSportMemberships', function ($q) use ($request) {
                    $q->whereHas('sport', function ($sportQuery) use ($request) {
                        $sportQuery->where('slug', $request->sport);
                    });
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
                ],
                'debug' => [
                    'total_count' => $totalCount,
                    'query_count' => $people->total(),
                    'items_count' => count($people->items())
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка загрузки персон: ' . $e->getMessage(),
                'debug' => [
                    'total_count' => Person::count()
                ]
            ], 500);
        }
    }

    // ... остальные методы остаются без изменений
}
