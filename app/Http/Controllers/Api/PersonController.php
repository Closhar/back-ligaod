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
            // Проверяем, является ли это асинхронным запросом
            $type = $request->query('type');
            $searchQuery = $request->query('q');

            // Если это асинхронный запрос, возвращаем упрощенный список
            if ($type === 'async') {
                $query = Person::select(['id', 'first_name', 'last_name', 'middle_name', 'birth_date'])
                    ->orderBy('last_name')
                    ->orderBy('first_name');

                // Добавляем поиск по ФИО и дате рождения
                if ($searchQuery) {
                    $query->where(function ($q) use ($searchQuery) {
                        $q->where('first_name', 'LIKE', "%{$searchQuery}%")
                          ->orWhere('last_name', 'LIKE', "%{$searchQuery}%")
                          ->orWhere('middle_name', 'LIKE', "%{$searchQuery}%")
                          ->orWhere('birth_date', 'LIKE', "%{$searchQuery}%");
                    });
                }

                $people = $query->limit(20)->get();

                // Формируем title для отображения: ФИО (дата рождения)
                $people->each(function ($person) {
                    $fullName = trim($person->last_name . ' ' . $person->first_name . ' ' . ($person->middle_name ?? ''));
                    $birthDate = $person->birth_date ? date('d.m.Y', strtotime($person->birth_date)) : '';
                    $person->title = $fullName . ($birthDate ? " ({$birthDate})" : '');
                });

                return response()->json($people);
            }

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

            // Фильтрация по клубу - пробуем разные варианты связей
            if ($request->has('club') && !empty($request->club)) {
                $query->where(function ($q) use ($request) {
                    // Вариант 1: через clubMemberships
                    $q->whereHas('clubMemberships', function ($subQ) use ($request) {
                        $subQ->whereHas('club', function ($clubQuery) use ($request) {
                            $clubQuery->where('slug', $request->club);
                        });
                    });

                    // Вариант 2: через прямую связь с клубами
                    $q->orWhereHas('clubs', function ($clubQuery) use ($request) {
                        $clubQuery->where('slug', $request->club);
                    });

                    // Вариант 3: через activeClubMemberships (на случай если это правильная связь)
                    $q->orWhereHas('activeClubMemberships', function ($subQ) use ($request) {
                        $subQ->whereHas('club', function ($clubQuery) use ($request) {
                            $clubQuery->where('slug', $request->club);
                        });
                    });
                });
            }

            // Фильтрация по спорту - пробуем разные варианты связей
            if ($request->has('sport') && !empty($request->sport)) {
                $query->where(function ($q) use ($request) {
                    // Вариант 1: через sportMemberships
                    $q->whereHas('sportMemberships', function ($subQ) use ($request) {
                        $subQ->whereHas('sport', function ($sportQuery) use ($request) {
                            $sportQuery->where('slug', $request->sport);
                        });
                    });

                    // Вариант 2: через прямую связь со спортами
                    $q->orWhereHas('sports', function ($sportQuery) use ($request) {
                        $sportQuery->where('slug', $request->sport);
                    });

                    // Вариант 3: через activeSportMemberships (на случай если это правильная связь)
                    $q->orWhereHas('activeSportMemberships', function ($subQ) use ($request) {
                        $subQ->whereHas('sport', function ($sportQuery) use ($request) {
                            $sportQuery->where('slug', $request->sport);
                        });
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

    /**
     * Получить конкретную персону с полной информацией
     */
    public function show(Person $person): JsonResponse
    {
        $person->load([
            'clubs' => function ($query) {
                $query->select([
                    'clubs.id',
                    'clubs.title',
                    'clubs.slug',
                    'clubs.image'
                ]);
            },
            'sports' => function ($query) {
                $query->select([
                    'sports.id',
                    'sports.title',
                    'sports.icon'
                ]);
            },
            'images',
            'surnameChanges',
            'clubMemberships.club',
            'sportMemberships.sport',
            'positionMemberships.position',
            'ampluaMemberships.amplua',
            'activeClubMemberships' => function ($query) {
                $query->with(['club' => function ($clubQuery) {
                    $clubQuery->select([
                        'clubs.id',
                        'clubs.title',
                        'clubs.slug',
                        'clubs.image'
                    ]);
                }]);
            },
            'gender' => function ($query) {
                $query->select([
                    'genders.id',
                    'genders.title'
                ]);
            },
            'mainImage'
        ]);

        // Формируем данные для фронтенда
        $personData = $person->toArray();

        // Добавляем photo_path если есть главное изображение
        if ($person->mainImage) {
            $personData['photo_path'] = config('app.url') . '/storage/' . $person->mainImage->path;
        }

        // Добавляем biography из поля about
        if ($person->about) {
            $personData['biography'] = $person->about;
        }

        return response()->json($personData);
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
            'birth_date' => 'nullable|date|before:today',
            'passport_series' => 'nullable|string|size:4',
            'passport_number' => 'nullable|string|size:6',
            'address' => 'nullable|string',
            'player_number' => 'nullable|integer|min:0',
            'gender' => 'required|string|in:m,f',
            'is_active' => 'sometimes|boolean',
            'about' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Проверка уникальности по ФИО и дате рождения
        $data = $validator->validated();
        if (!isset($data['gender']) || !$data['gender']) {
            $data['gender'] = 'm';
        }
        $exists = \App\Models\Person::where('first_name', $data['first_name'])
            ->where('last_name', $data['last_name'])
            ->where('middle_name', $data['middle_name'] ?? null)
            ->where('birth_date', $data['birth_date'] ?? null)
            ->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'unique' => ['Персона с такими ФИО и датой рождения уже существует.']
                ]
            ], 422);
        }

        $person = Person::create($data);

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
            'birth_date' => 'nullable|date|before:today',
            'passport_series' => 'nullable|string|size:4',
            'passport_number' => 'nullable|string|size:6',
            'address' => 'nullable|string',
            'player_number' => 'nullable|integer|min:0',
            'gender' => 'nullable|string|in:m,f',
            'is_active' => 'sometimes|boolean',
            'about' => 'nullable|string',
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
            'with_positions' => Person::whereHas('activePositionMemberships')->count(),
            'with_ampluas' => Person::whereHas('activeAmpluaMemberships')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Получить список команд для фильтрации
     */
    public function clubs(): JsonResponse
    {
        try {
            $type = request()->query('type');
            $searchQuery = request()->query('q');
            $limit = request()->query('limit', 10);

            $query = Club::with(['city', 'sport', 'gender']);

            // Если это асинхронный запрос, добавляем поиск
            if ($type === 'async' && $searchQuery) {
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }

            $clubs = $query->orderBy('title')->limit($limit)->get();

            // Добавляем поля для совместимости с фронтендом
            $clubs->each(function ($club) {
                $club->name = $club->full_info;
                $club->logo_url = $club->club_image_path;
            });

            return response()->json([
                'success' => true,
                'data' => $clubs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка загрузки команд: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить список видов спорта для фильтрации
     */
    public function sports(): JsonResponse
    {
        try {
            $type = request()->query('type');
            $searchQuery = request()->query('q');
            $limit = request()->query('limit', 10);

            $query = Sport::query();

            // Если это асинхронный запрос, добавляем поиск
            if ($type === 'async' && $searchQuery) {
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }

            $sports = $query->orderBy('title')->limit($limit)->get();

            // Добавляем поля для совместимости с фронтендом
            $sports->each(function ($sport) {
                $sport->name = $sport->title;
                $sport->icon_name = $sport->icon;
                $sport->icon = $sport->icon;
            });

            return response()->json([
                'success' => true,
                'data' => $sports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка загрузки видов спорта: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить список должностей для фильтрации
     */
    public function positions(): JsonResponse
    {
        try {
            $type = request()->query('type');
            $searchQuery = request()->query('q');
            $limit = request()->query('limit', 10);

            $query = Position::where('is_active', true);

            // Если это асинхронный запрос, добавляем поиск
            if ($type === 'async' && $searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $positions = $query->orderBy('name')->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $positions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка загрузки должностей: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить список амплуа для фильтрации
     */
    public function ampluas(): JsonResponse
    {
        try {
            $type = request()->query('type');
            $searchQuery = request()->query('q');
            $limit = request()->query('limit', 10);

            $query = Amplua::where('is_active', true);

            // Если это асинхронный запрос, добавляем поиск
            if ($type === 'async' && $searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $ampluas = $query->orderBy('name')->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $ampluas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка загрузки амплуа: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Поиск персон по ФИО и дате рождения (для автокомплита)
     */
    public function search(Request $request): JsonResponse
    {
        $lastName = $request->get('last_name');
        $firstName = $request->get('first_name');
        $middleName = $request->get('middle_name');
        $birthDate = $request->get('birth_date');
        $query = $request->get('query', '');

        $people = Person::query()
            ->when($lastName, function ($q) use ($lastName) {
                $q->where('last_name', 'like', "%$lastName%");
            })
            ->when($firstName, function ($q) use ($firstName) {
                $q->where('first_name', 'like', "%$firstName%");
            })
            ->when($middleName, function ($q) use ($middleName) {
                $q->where('middle_name', 'like', "%$middleName%");
            })
            ->when($birthDate, function ($q) use ($birthDate) {
                $q->whereDate('birth_date', $birthDate);
            })
            ->when(!$lastName && !$firstName && !$middleName && $query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('last_name', 'like', "%$query%")
                        ->orWhere('first_name', 'like', "%$query%")
                        ->orWhere('middle_name', 'like', "%$query%")
                        ->orWhereRaw("CONCAT(last_name, ' ', first_name, ' ', middle_name) like ?", ["%$query%"]);
                });
            })
            ->orderBy('last_name')
            ->limit(20)
            ->get();

        $result = $people->map(function ($person) {
            $label = $person->full_name;
            if ($person->birth_date) {
                $label .= ' (' . $person->birth_date->format('d.m.Y') . ')';
            }
            return [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'birth_date' => $person->birth_date ? $person->birth_date->format('Y-m-d') : null,
                'label' => $label,
            ];
        });

        return response()->json($result);
    }

    /**
     * Получить изображения персоны
     */
    public function getImages(Person $person): JsonResponse
    {
        try {
            $images = $person->images()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_path' => $image->image_path,
                        'image_url' => $image->image_url, // Добавляем image_url
                        'alt_text' => $image->alt_text ?? '',
                        'created_at' => $image->created_at?->toISOString(),
                        'updated_at' => $image->updated_at?->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $images
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка получения изображений персоны', [
                'person_id' => $person->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения изображений: ' . $e->getMessage()
            ], 500);
        }
    }
}
