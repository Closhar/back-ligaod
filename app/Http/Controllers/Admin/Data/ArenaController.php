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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class ArenaController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        // Получаем параметры фильтрации из запроса
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $searchQuery = $request->input('q');
        $regionId = $request->input('region_id');
        $cityId = $request->input('city_id');
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'asc');

        // Основной запрос с фильтрацией
        $query = Arena::query()
            ->select('id', 'region_id', 'title', 'slug', 'city_id', 'about', 'sites', 'vks', 'youtubes',
                'emails', 'phones', 'telegrams', 'instagrams', 'facebooks', 'xs', 'address',
                'dop_info', 'map', 'image', 'gallery_id')
            ->with([
                'region' => function ($query) {
                    $query->select('id', 'title', 'title_short', 'subdomain');
                },
                'city' => function ($query) {
                    $query->select('id', 'title', 'title_short');
                }
            ]);

        // Применяем фильтры
        if ($regionId) {
            $query->where('region_id', $regionId);
        }

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        // Применяем поиск
        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('title', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('address', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('about', 'LIKE', "%{$searchQuery}%")
                    ->orWhereHas('city', function ($cityQuery) use ($searchQuery) {
                        $cityQuery->where('title', 'LIKE', "%{$searchQuery}%");
                    });
            });
        }

        // Применяем сортировку
        $query->orderBy($sortField, $sortDirection);

        // Получаем данные с пагинацией
        $arenas = $query->paginate($perPage, ['*'], 'page', $page);

        // Преобразуем данные для ответа
        $transformedArenas = $arenas->map(function ($arena) {
            return [
                'id' => $arena->id,
                'title' => $arena->title,
                'slug' => $arena->slug,
                'about' => $arena->about,
                'sites' => $arena->sites,
                'vks' => $arena->vks,
                'youtubes' => $arena->youtubes,
                'emails' => $arena->emails,
                'phones' => $arena->phones,
                'telegrams' => $arena->telegrams,
                'instagrams' => $arena->instagrams,
                'facebooks' => $arena->facebooks,
                'xs' => $arena->xs,
                'address' => $arena->address,
                'dop_info' => $arena->dop_info,
                'map' => $arena->map,
                'image' => $arena->image,
                'gallery_id' => $arena->gallery_id,
                'region' => $arena->region,
                'city' => $arena->city,
                'image_path' => $arena->image ? config('app.url') . '/storage/' . $arena->image : null,
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

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:arenas',
                'region_id' => 'required|integer|exists:regions,id',
                'city_id' => 'required_without:city_title|exists:cities,id',
                'city_title' => 'required_without:city_id|string|max:255',
                'about' => 'nullable|string',
                'sites' => 'nullable|string',
                'vks' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'emails' => 'nullable|string',
                'phones' => 'nullable|string',
                'telegrams' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'address' => 'nullable|string',
                'dop_info' => 'nullable|string',
                'map' => 'nullable|string',
                'gallery_id' => 'nullable|integer|exists:galleries,id',
            ]);

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

            $item = Arena::create($validated);

            return response()->json($item, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
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
            $arena = Arena::findOrFail($id);

            $validated = $request->validate([
                'title' => 'string|max:255',
                'slug' => 'string|max:255|unique:arenas,slug,' . $id,
                'region_id' => 'integer|exists:regions,id',
                'city_id' => 'integer|exists:cities,id',
                'about' => 'nullable|string',
                'sites' => 'nullable|string',
                'vks' => 'nullable|string',
                'youtubes' => 'nullable|string',
                'emails' => 'nullable|string',
                'phones' => 'nullable|string',
                'telegrams' => 'nullable|string',
                'instagrams' => 'nullable|string',
                'facebooks' => 'nullable|string',
                'xs' => 'nullable|string',
                'address' => 'nullable|string',
                'dop_info' => 'nullable|string',
                'map' => 'nullable|string',
                'gallery_id' => 'nullable|integer|exists:galleries,id',
            ]);

            $arena->update($validated);

            return response()->json([
                'success' => true,
                'data' => $arena,
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
                'error' => $e->getMessage()
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

    public function uploadImage(Request $request, $id)
    {
        try {
            $model = Arena::findOrFail($id);
            $field = $request->input('field', 'image');

            $validator = Validator::make($request->all(), [
                'image' => [
                    'required',
                    'image',
                    'mimes:jpeg,png,jpg,gif,webp',
                    'max:2048' // 2MB
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
            $path = $request->file('image')->store('arenas', 'public');

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
            $model = Arena::findOrFail($id);
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

    public function checkFieldFreshness(Request $request, $id)
    {
        try {
            $field = $request->query('field');
            $value = $request->query('value');

            if (!$field) {
                return response()->json([
                    'success' => false,
                    'message' => 'Параметр field обязателен'
                ], 400);
            }

            $arena = Arena::findOrFail($id);

            if (!array_key_exists($field, $arena->getAttributes())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Указанное поле не существует'
                ], 400);
            }

            $serverValue = $arena->$field;
            $isFresh = $serverValue === $value;

            return response()->json([
                'is_fresh' => $isFresh,
                'server_value' => $isFresh ? null : $serverValue,
                'updated_at' => $isFresh ? null : $arena->updated_at
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }
    }
}
