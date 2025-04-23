<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $searchQuery = $request->input('q');
            $type = $request->input('type');
            $sortField = $request->input('sort_field', 'id');
            $sortDirection = $request->input('sort_direction', 'asc');
            $limit = $request->input('limit', $perPage);

            $query = Image::query()
                ->select([
                    'images.id',
                    'images.title',
                    'images.image',
                    'images.gallery_id',
                    'images.position',
                    DB::raw('CONCAT("' . config('app.url') . '", "/storage/", images.image) AS full_image_path')
                ])
                ->with('gallery')
                ->orderBy($sortField, $sortDirection);

            if ($searchQuery && !$request->has('field')) {
                $query->where('images.title', 'LIKE', "%{$searchQuery}%");
            }

            if ($request->has('field')) {
                $field = $request->input('field');
                $value = urldecode($request->input('q', ''));

                $allowedFields = ['id', 'title', 'gallery_id', 'position'];

                if (in_array($field, $allowedFields)) {
                    if (in_array($field, ['title'])) {
                        $query->where('images.' . $field, 'LIKE', "%{$value}%");
                    } else {
                        $query->where('images.' . $field, $value);
                    }
                }
            }

            if ($request->has('gallery_id') && $request->input('gallery_id') !== null) {
                $query->where('images.gallery_id', $request->input('gallery_id'));
            }

            if ($type || ($request->has('limit') && !$request->has('page'))) {
                return $query->limit($limit)->get()->toArray();
            }

            $images = $query->paginate($perPage);

            $result = [
                'current_page' => $images->currentPage(),
                'data' => $images->items(),
                'first_page_url' => $images->url(1),
                'from' => $images->firstItem(),
                'last_page' => $images->lastPage(),
                'last_page_url' => $images->url($images->lastPage()),
                'links' => $images->links(),
                'next_page_url' => $images->nextPageUrl(),
                'path' => $images->path(),
                'per_page' => $images->perPage(),
                'prev_page_url' => $images->previousPageUrl(),
                'to' => $images->lastItem(),
                'total' => $images->total(),
            ];

            if (config('app.debug')) {
                $result['_debug'] = [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                    'requested_field' => $request->input('field'),
                    'requested_q' => $request->input('q'),
                    'count_results' => $images->total(),
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

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'image' => 'required|string|max:255',
                'gallery_id' => 'required|exists:galleries,id',
                'position' => 'nullable|integer'
            ]);

            $item = Image::create($validated);

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
            $item = Image::findOrFail($id);
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
                'image' => 'string|max:255',
                'gallery_id' => 'exists:galleries,id',
                'position' => 'nullable|integer'
            ]);

            $item = Image::findOrFail($id);
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
            $item = Image::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function uploadImage(Request $request, $id, $folder = 'images')
    {
        try {
            $model = Image::findOrFail($id);
            $field = $request->input('field', 'image');

            $validator = Validator::make($request->all(), [
                'image' => [
                    'required',
                    'image',
                    'mimes:jpeg,png,jpg,gif,webp',
                    'max:2048'
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

            $path = $request->file('image')->store($folder, 'public');

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
            $model = Image::findOrFail($id);
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
}
