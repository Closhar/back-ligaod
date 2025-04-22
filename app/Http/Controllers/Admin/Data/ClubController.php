<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\Club;
use App\Models\Event;
use App\Models\Gender;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
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
            $sortField = $request->input('sort_field', 'id'); // Поле для сортировки
            $sortDirection = $request->input('sort_direction', 'asc'); // Направление сортировки
            $perPage = $request->query('per_page', 10); // Количество элементов на странице (по умолчанию 10)
            $limit = $request->input('limit', $perPage);

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
                    'clubs.is_alien',
                    'clubs.image',
                    'clubs.image_bg',
                    DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short) AS club_info'),
                    DB::raw('CONCAT("' . config('app.url') . '", "/storage/", clubs.image) AS full_image_path'),
                    DB::raw('CONCAT("' . config('app.url') . '", "/storage/", clubs.image_bg) AS full_image_bg_path')
                    ])
                ->join('sports as sport', 'clubs.sport_id', '=', 'sport.id')
                ->join('cities as city', 'clubs.city_id', '=', 'city.id')
                ->join('genders as gender', 'clubs.gender_id', '=', 'gender.id')
                ->with('city')
                ->with('gender')
                ->with('sport')
                ->orderBy($sortField, $sortDirection); // Применение сортировки

            // Общий поиск (работает только по заголовку, если нет параметра field)
            if ($searchQuery && !$request->has('field')) {
                $query->where(function($q) use ($searchQuery) {
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
                    'city_id', 'sport_id', 'gender_id', 'age_id', 'is_alien',
                    'club_info'
                ];

                if (in_array($field, $allowedFields)) {
                    // Для текстовых полей используем LIKE
                    if (in_array($field, ['title', 'title_short', 'about', 'address', 'slug', 'club_info'])) {
                        if ($field === 'club_info') {
                            $query->where(DB::raw('CONCAT(clubs.title, " (", city.title_short, ") | ", sport.title_short, " | ", gender.title_short)'), 'LIKE', "%{$value}%");
                        } else {
                            $query->where('clubs.' . $field, 'LIKE', "%{$value}%");
                        }
                    } else {
                        // Для других полей используем точное соответствие
                        $query->where('clubs.' . $field, $value);
                    }
                }
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

            if ($request->has('id')) {
                $query->where('clubs.id', $request->input('id'));
            }
            // ... остальные фильтры

            // Если запрошен простой вывод или указан лимит, но не запрошена пагинация
            if ($type || ($request->has('limit') && !$request->has('page'))) {
                // Используем указанный лимит или значение по умолчанию
                $resultLimit = $request->input('limit', 10);
                return $query->limit($resultLimit)->get()->toArray();
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
                    'limit' => $request->input('limit')
                ];
            }

            return $result;

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
                'title_short' => 'nullable|string|max:100',
                'about' => 'nullable|string',
                'address' => 'nullable|string|max:500',
                'phones' => 'nullable|string',
                'emails' => 'nullable|string',
                'sites' => 'nullable|string',
                'vks' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'map' => 'nullable|string',
                'slug' => 'nullable|string|max:255|unique:clubs,slug',
                'city_id' => 'required_without:city_title|exists:cities,id',
                'city_title' => 'required_without:city_id|string|max:255',
                'gallery_id' => 'nullable|exists:galleries,id',
                'sport_id' => 'required|exists:sports,id',
                'gender_id' => 'required|exists:genders,id',
                'age_id' => 'nullable|exists:ages,id',
                'is_alien' => 'boolean',
                'image' => 'nullable|string|max:255',
                'image_bg' => 'nullable|string|max:255'
            ]);

            // Обработка city_title
            if (isset($validated['city_title'])) {
                $city = \App\Models\City::where('title', $validated['city_title'])->first();

                if (!$city) {
                    $city = \App\Models\City::create([
                        'title' => $validated['city_title'],
                        'title_short' => $validated['city_title'],
                        'slug' => \Illuminate\Support\Str::slug($validated['city_title'])
                    ]);
                }

                $validated['city_id'] = $city->id;
                unset($validated['city_title']);
            }

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
            $validated = $request->validate([
                'title' => 'string|max:255',
                'title_short' => 'string|max:100',
                'about' => 'nullable|string',
                'address' => 'nullable|string|max:500',
                'phones' => 'nullable|string',
                'emails' => 'nullable|string',
                'sites' => 'nullable|string',
                'vks' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'map' => 'nullable|string',
                'slug' => 'nullable|string|max:255|unique:clubs,slug,' . $id,
                'city_id' => 'exists:cities,id',
                'gallery_id' => 'nullable|exists:galleries,id',
                'sport_id' => 'exists:sports,id',
                'gender_id' => 'exists:genders,id',
                'age_id' => 'nullable|exists:ages,id',
                'is_alien' => 'boolean',
                'image' => 'nullable|string|max:255',
                'image_bg' => 'nullable|string|max:255'
            ]);

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
                    'max:2048' // 10MB
                ],
                'field' => 'sometimes|string'
            ], [
                'image.required' => 'Файл изображения обязателен',
                'image.image' => 'Файл должен быть изображением',
                'image.mimes' => 'Допустимые форматы: jpeg, png, jpg, gif, webp',
                'image.max' => 'Максимальный размер файла 2MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
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
                'message' => 'Изображение успешно загружено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteImage(Request $request, $id)
    {
        try {
            $model = Club::findOrFail($id);
            $field = $request->input('field', 'image');

            if (!$model->{$field}) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет изображения для удаления'
                ], 404);
            }

            Storage::disk('public')->delete($model->{$field});
            $model->{$field} = null;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => 'Изображение успешно удалено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении изображения: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyImage($id, $field = 'image')
    {
        try {
            $model = Event::findOrFail($id);

            if (!$model->{$field}) {
                return response()->json([
                    'success' => false,
                    'message' => 'No image to delete'
                ], 404);
            }

            Storage::disk('public')->delete($model->{$field});
            $model->{$field} = null;
            $model->save();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting image',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
