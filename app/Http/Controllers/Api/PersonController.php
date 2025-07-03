<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amplua;
use App\Models\Club;
use App\Models\Person;
use App\Models\Position;
use App\Models\Sport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    $query->select(['id', 'title', 'image', 'city_id', 'sport_id', 'gender_id']); // Выбираем поля для full_info
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
            'clubs',
            'sports',
            'images',
            'surnameChanges',
            'clubMemberships.club',
            'sportMemberships.sport',
            'positionMemberships.position',
            'ampluaMemberships.amplua'
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
            'birth_date' => 'nullable|date|before:today',
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
            'birth_date' => 'nullable|date|before:today',
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
                $club->logo_url = $club->club_image_path; // Добавляем URL логотипа
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
                $sport->icon_name = $sport->icon; // Добавляем название иконки
                $sport->icon = $sport->icon; // Добавляем иконку для отображения
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
     * Получить изображения пользователя
     */
    public function getImages(Person $person): JsonResponse
    {
        try {
            $images = $person->images()->orderBy('position')->get();

            // Если нет главного изображения, но есть изображения, назначаем первое главным
            if ($images->count() > 0 && !$images->where('is_main', true)->count()) {
                $firstImage = $images->first();
                $firstImage->update(['is_main' => true]);
                $images = $person->images()->orderBy('position')->get(); // Обновляем коллекцию
            }

            return response()->json([
                'success' => true,
                'data' => $images
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка загрузки изображений: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Загрузить изображение пользователя
     */
    public function uploadImage(Request $request, Person $person): JsonResponse
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:6144'
            ]);

            $file = $request->file('image');
            $path = $file->store('people/' . $person->id . '/images', 'public');

            // Оптимизируем изображение
            $fullPath = Storage::disk('public')->path($path);
            $this->optimizeImage($fullPath, 500, 750);

            // Определяем позицию (последняя + 1)
            $nextPosition = $person->images()->max('position') + 1;

            // Определяем, будет ли это главное изображение
            // Если это первое изображение пользователя, то оно становится главным
            $isMain = $person->images()->count() === 0;

            $image = $person->images()->create([
                'image_path' => $path,
                'image_url' => $path,
                'title' => $file->getClientOriginalName(),
                'position' => $nextPosition,
                'is_main' => $isMain
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Изображение успешно загружено',
                'data' => $image
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка загрузки изображения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить изображение пользователя
     */
    public function deleteImage(Person $person, $imageId): JsonResponse
    {
        try {
            $image = $person->images()->findOrFail($imageId);
            $wasMain = $image->is_main;

            // Удаляем файл
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }

            $image->delete();

            // Если удалили главное изображение, назначаем главным первое оставшееся
            if ($wasMain) {
                $remainingImages = $person->images()->orderBy('position')->get();
                if ($remainingImages->count() > 0) {
                    $remainingImages->first()->update(['is_main' => true]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Изображение успешно удалено'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка удаления изображения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить несколько изображений пользователя
     */
    public function deleteMultipleImages(Request $request, Person $person): JsonResponse
    {
        try {
            $request->validate([
                'image_ids' => 'required|array',
                'image_ids.*' => 'integer|exists:person_images,id'
            ]);

            $images = $person->images()->whereIn('id', $request->image_ids)->get();
            $deletedMainImage = false;

            foreach ($images as $image) {
                if ($image->is_main) {
                    $deletedMainImage = true;
                }

                // Удаляем файл
                if (Storage::disk('public')->exists($image->image_path)) {
                    Storage::disk('public')->delete($image->image_path);
                }
                $image->delete();
            }

            // Если удалили главное изображение, назначаем главным первое оставшееся
            if ($deletedMainImage) {
                $remainingImages = $person->images()->orderBy('position')->get();
                if ($remainingImages->count() > 0) {
                    $remainingImages->first()->update(['is_main' => true]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Изображения успешно удалены'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка удаления изображений: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Установить главное изображение
     */
    public function setMainImage(Person $person, $imageId): JsonResponse
    {
        try {
            $image = $person->images()->findOrFail($imageId);

            // Сбрасываем главное изображение у всех изображений пользователя
            $person->images()->update(['is_main' => false]);

            // Устанавливаем новое главное изображение
            $image->update(['is_main' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Главное изображение установлено',
                'data' => [
                    'image_id' => $image->id,
                    'is_main' => true
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка установки главного изображения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отменить главность изображения
     */
    public function unsetMainImage(Person $person, $imageId): JsonResponse
    {
        try {
            $image = $person->images()->findOrFail($imageId);

            // Снимаем главность с текущего изображения
            $image->update(['is_main' => false]);

            // Назначаем главным первое изображение по позиции
            $firstImage = $person->images()->orderBy('position')->first();
            if ($firstImage) {
                $firstImage->update(['is_main' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Главность изображения отменена',
                'data' => [
                    'image_id' => $image->id,
                    'is_main' => false,
                    'new_main_image_id' => $firstImage ? $firstImage->id : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка отмены главности изображения: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить позиции изображений
     */
    public function updateImagePositions(Request $request, Person $person): JsonResponse
    {
        try {
            $request->validate([
                'positions' => 'required|array',
                'positions.*.image_id' => 'required|integer|exists:person_images,id',
                'positions.*.position' => 'required|integer|min:1'
            ]);

            foreach ($request->positions as $positionData) {
                $person->images()
                    ->where('id', $positionData['image_id'])
                    ->update(['position' => $positionData['position']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Позиции изображений обновлены'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка обновления позиций: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Оптимизировать изображение
     */
    private function optimizeImage($imagePath, $maxWidth, $maxHeight)
    {
        if (!extension_loaded('gd')) {
            return; // Если GD не установлен, пропускаем оптимизацию
        }

        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return;
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // Вычисляем новые размеры с сохранением пропорций
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);

        // Создаем новое изображение
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Загружаем оригинальное изображение
        switch ($mimeType) {
            case 'image/jpeg':
                $originalImage = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $originalImage = imagecreatefrompng($imagePath);
                // Сохраняем прозрачность для PNG
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                break;
            case 'image/gif':
                $originalImage = imagecreatefromgif($imagePath);
                break;
            default:
                return;
        }

        // Изменяем размер
        imagecopyresampled($newImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Сохраняем оптимизированное изображение
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($newImage, $imagePath, 85); // Качество 85%
                break;
            case 'image/png':
                imagepng($newImage, $imagePath, 6); // Сжатие 6
                break;
            case 'image/gif':
                imagegif($newImage, $imagePath);
                break;
        }

        // Освобождаем память
        imagedestroy($originalImage);
        imagedestroy($newImage);
    }
}
