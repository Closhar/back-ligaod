<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Club;
use App\Models\Event;
use App\Models\PersonClubMembership;
use App\Models\PersonImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
            $page = $request->input('page', 1);
            $searchQuery = $request->input('q');
            $type = $request->input('type');
            $sortField = $request->input('sort_field', 'id'); // Поле для сортировки
            $sortDirection = $request->input('sort_direction', 'asc'); // Направление сортировки
            $perPage = $request->query('per_page', 10); // Количество элементов на странице (по умолчанию 10)
            $limit = $request->input('limit', $perPage);
            $offset = $request->input('offset', 0); // Смещение для пагинации

            // Основной запрос
            $query = Club::query()
                ->select([
                    'clubs.id',
                    'clubs.title',
                    'clubs.title_short',
                    'clubs.about',
                    'clubs.address',
                    'clubs.phones',
                    'clubs.emails',
                    'clubs.sites',
                    'clubs.telegrams',
                    'clubs.vks',
                    'clubs.instagrams',
                    'clubs.youtubes',
                    'clubs.facebooks',
                    'clubs.xs',
                    'clubs.map',
                    'clubs.slug',
                    'clubs.city_id',
                    'clubs.gallery_id',
                    'clubs.sport_id',
                    'clubs.gender_id',
                    'clubs.age_id',
                    'clubs.region_id',
                    'clubs.rating_region_id',
                    'clubs.is_alien',
                    'clubs.image',
                    'clubs.image_bg',
                    'clubs.tlgs_to_parse',
                    DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short) AS club_info'),
                ])
                ->join('sports as sport', 'clubs.sport_id', '=', 'sport.id')
                ->join('cities as city', 'clubs.city_id', '=', 'city.id')
                ->join('genders as gender', 'clubs.gender_id', '=', 'gender.id')
                ->leftJoin('rating_regions as rating_region', 'clubs.rating_region_id', '=', 'rating_region.id')
                ->with('city')
                ->with('gender')
                ->with('sport')
                ->with('region')
                ->with('ratingRegion')
                ->with('arenas')
                ->with('gallery')
                ->withCount('arenas')
                ->orderBy($sortField, $sortDirection); // Применение сортировки

            // Общий поиск (работает только по заголовку, если нет параметра field)
            if ($searchQuery && ! $request->has('field')) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('clubs.title', 'LIKE', "%{$searchQuery}%")
                        ->orWhere(DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short)'), 'LIKE', "%{$searchQuery}%");
                });
            }

            // Фильтрация по произвольному полю
            if ($request->has('field')) {
                $field = $request->input('field');
                // Получаем значение из параметра q и декодируем URL-кодирование
                $value = urldecode($request->input('q', ''));

                // Проверка, является ли поле допустимым для избежания SQL-инъекций
                $allowedFields = [
                    'id', 'title', 'title_short', 'about', 'address', 'slug',
                    'city_id', 'sport_id', 'gender_id', 'age_id', 'region_id', 'rating_region_id', 'is_alien',
                    'club_info', 'tlgs_to_parse',
                ];

                if (in_array($field, $allowedFields)) {
                    // Для текстовых полей используем LIKE
                    if (in_array($field, ['title', 'title_short', 'about', 'address', 'slug', 'club_info'])) {
                        if ($field === 'club_info') {
                            $query->where(DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short)'), 'LIKE', "%{$value}%");
                        } else {
                            $query->where('clubs.'.$field, 'LIKE', "%{$value}%");
                        }
                    } else {
                        // Для других полей используем точное соответствие
                        $query->where('clubs.'.$field, $value);
                    }
                }
            }

            // Дополнительные фильтры (остаются без изменений)
            if ($request->has('sport') && $request->input('sport') !== null) {
                $query->whereHas('sport', fn ($q) => $q->where('slug', $request->input('sport')));
            }

            if ($request->has('gender_id') && $request->input('gender_id') !== null) {
                $query->where('clubs.gender_id', $request->input('gender_id'));
            }

            if ($request->has('sport_id') && $request->input('sport_id') !== null) {
                $query->where('clubs.sport_id', $request->input('sport_id'));
            }

            if ($request->has('city_id') && $request->input('city_id') !== null) {
                $query->where('clubs.city_id', $request->input('city_id'));
            }

            if ($request->has('region_id') && $request->input('region_id') !== null) {
                $query->where('clubs.region_id', $request->input('region_id'));
            }

            if ($request->has('rating_region_id') && $request->input('rating_region_id') !== null) {
                $query->where('clubs.rating_region_id', $request->input('rating_region_id'));
            }

            if ($request->has('id') && $request->input('id') !== null) {
                $query->where('clubs.id', $request->input('id'));
            }
            // ... остальные фильтры

            // Если запрошен простой вывод или указан лимит, но не запрошена пагинация
            if ($type || ($request->has('limit') && ! $request->has('page'))) {
                // Используем указанный лимит или значение по умолчанию
                $resultLimit = $request->input('limit', 10);

                return $query->limit($resultLimit)->get()->toArray();
            }

            // Применяем смещение если указано
            if ($offset > 0) {
                $query->skip($offset);
            }

            // Получаем SQL запрос для отладки
            $sql = $query->toSql();
            $bindings = $query->getBindings();

            // Стандартный вывод с пагинацией
            $clubs = $query->paginate($perPage);

            // Количество результатов для информирования
            $countResults = $clubs->total();

            $result = [
                'current_page' => $clubs->currentPage(),
                'data' => $clubs->items(),
                'first_page_url' => $clubs->url(1),
                'from' => $clubs->firstItem(),
                'last_page' => $clubs->lastPage(),
                'last_page_url' => $clubs->url($clubs->lastPage()),
                'links' => $clubs->links(),
                'next_page_url' => $clubs->nextPageUrl(),
                'path' => $clubs->path(),
                'per_page' => $clubs->perPage(),
                'prev_page_url' => $clubs->previousPageUrl(),
                'to' => $clubs->lastItem(),
                'total' => $clubs->total(),
            ];

            // Добавляем отладочную информацию в режиме разработки
            if (config('app.debug')) {
                $result['_debug'] = [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'requested_field' => $request->input('field'),
                    'requested_q' => $request->input('q'),
                    'decoded_q' => $value ?? null,
                    'count_results' => $countResults,
                    'limit' => $request->input('limit'),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Логируем входящие данные
            Log::info('Club creation request', [
                'data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'title_short' => 'nullable|string|max:100',
                'about' => 'nullable|string',
                'address' => 'nullable|string|max:500',
                'phones' => 'nullable|string',
                'emails' => 'nullable|string',
                'sites' => 'nullable|string',
                'vks' => 'nullable|string',
                'telegrams' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'map' => 'nullable|string',
                'tlgs_to_parse' => 'nullable|string',
                'slug' => 'nullable|string|max:255|unique:clubs,slug',
                'city_id' => 'nullable|exists:cities,id',
                'city_title' => 'nullable|string|max:255',
                'gallery_id' => 'nullable|exists:galleries,id',
                'sport_id' => 'required|exists:sports,id',
                'gender_id' => 'required|exists:genders,id',
                'age_id' => 'nullable|exists:ages,id',
                'is_alien' => 'boolean',
                'image' => 'nullable|string|max:255',
                'image_bg' => 'nullable|string|max:255',
                'region_id' => 'nullable|exists:regions,id',
                'rating_region_id' => 'nullable|exists:rating_regions,id',
            ]);

            Log::info('Validation passed', ['validated_data' => $validated]);

            // Обработка city_title
            if (isset($validated['city_title']) && ! empty($validated['city_title'])) {
                Log::info('Processing city_title', ['city_title' => $validated['city_title']]);

                $city = City::where('title', $validated['city_title'])->first();

                if (! $city) {
                    Log::info('Creating new city', ['city_title' => $validated['city_title']]);
                    $city = City::create([
                        'title' => $validated['city_title'],
                        'title_short' => mb_substr($validated['city_title'], 0, 3),
                    ]);
                    Log::info('City created', ['city_id' => $city->id]);
                }

                $validated['city_id'] = $city->id;
                unset($validated['city_title']);
            }

            // Убеждаемся, что обязательные поля присутствуют
            if (! isset($validated['city_id'])) {
                Log::warning('City ID is missing');

                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['city_id' => ['City is required']],
                ], 422);
            }

            Log::info('Creating club', ['final_data' => $validated]);

            $item = Club::create($validated);

            Log::info('Club created successfully', ['club_id' => $item->id]);

            return response()->json($item, 201);

        } catch (ValidationException $e) {
            Log::warning('Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Club creation error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
            ]);

            return response()->json([
                'message' => 'Internal Server Error',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            // Определяем, является ли параметр числовым ID или строковым slug
            $isNumeric = is_numeric($id);

            $query = Club::with('arenas')
                ->with('ratingRegion')
                ->withCount('arenas');

            if ($isNumeric) {
                $item = $query->findOrFail($id);
            } else {
                $item = $query->where('slug', $id)->firstOrFail();
            }

            // Получаем активные членства (игроки этого клуба, у которых left_at = null)
            $activeMemberships = PersonClubMembership::with([
                'person.activeAmpluaMemberships.amplua',
                'person.mainImage',
                'person.positionMemberships.position', // добавлено для сотрудников
            ])
                ->where('club_id', $item->id)
                ->whereNull('left_at')
                ->get();

            // Загружаем изображения отдельно для каждого игрока
            foreach ($activeMemberships as $membership) {
                if ($membership->person) {
                    $images = PersonImage::where('person_id', $membership->person->id)
                        ->orderBy('position')
                        ->get();
                    $membership->person->setRelation('images', $images);
                }
            }

            $itemArr = $item->toArray();
            $itemArr['active_memberships'] = $activeMemberships->toArray();

            return response()->json($itemArr);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'string|max:255',
                'title_short' => 'string|max:100',
                'about' => 'nullable|string',
                'address' => 'nullable|string|max:500',
                'phones' => 'nullable|string',
                'emails' => 'nullable|string',
                'sites' => 'nullable|string',
                'vks' => 'nullable|string',
                'telegrams' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'map' => 'nullable|string',
                'tlgs_to_parse' => 'nullable|string',
                'slug' => 'nullable|string|max:255|unique:clubs,slug,'.$id,
                'city_id' => 'exists:cities,id',
                'gallery_id' => 'nullable|exists:galleries,id',
                'sport_id' => 'exists:sports,id',
                'gender_id' => 'exists:genders,id',
                'age_id' => 'nullable|exists:ages,id',
                'is_alien' => 'boolean',
                'image' => 'nullable|string|max:255',
                'image_bg' => 'nullable|string|max:255',
                'region_id' => 'nullable|exists:regions,id',
                'rating_region_id' => 'nullable|exists:rating_regions,id',
            ]);

            $item = Club::findOrFail($id);
            $item->update($validated);

            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Updated successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => config('app.debug') ? $e->getMessage() : null,
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

    public function uploadImage(Request $request, $id, $folder = 'clubs')
    {
        try {
            $model = Club::findOrFail($id);
            $field = $request->input('field', 'image');

            $validator = Validator::make($request->all(), [
                'image' => [
                    'required',
                    'image',
                    'mimes:jpeg,png,jpg,gif,webp',
                    'max:2048', // 10MB
                ],
                'field' => 'sometimes|string',
            ], [
                'image.required' => 'Файл изображения обязателен',
                'image.image' => 'Файл должен быть изображением',
                'image.mimes' => 'Допустимые форматы: jpeg, png, jpg, gif, webp',
                'image.max' => 'Максимальный размер файла 2MB',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Обработка изображения
            $path = $request->file('image')->store($folder, 'public');

            // Удаляем старое изображение
            if ($model->{$field}) {
                Storage::disk('public')->delete($model->{$field});
            }

            $model->{$field} = $path;
            $model->save();

            return response()->json([
                'success' => true,
                'image_path' => $path,
                'full_path' => Storage::disk('public')->url($path),
                'message' => 'Изображение успешно загружено',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteImage(Request $request, $id)
    {
        try {
            $model = Club::findOrFail($id);
            $field = $request->input('field', 'image');

            if (! $model->{$field}) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет изображения для удаления',
                ], 404);
            }

            Storage::disk('public')->delete($model->{$field});
            $model->{$field} = null;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => 'Изображение успешно удалено',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении изображения: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroyImage($id, $field = 'image')
    {
        try {
            $model = Event::findOrFail($id);

            if (! $model->{$field}) {
                return response()->json([
                    'success' => false,
                    'message' => 'No image to delete',
                ], 404);
            }

            Storage::disk('public')->delete($model->{$field});
            $model->{$field} = null;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting image',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
