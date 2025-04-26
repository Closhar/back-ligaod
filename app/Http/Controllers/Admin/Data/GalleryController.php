<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Storage;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $searchQuery = $request->input('q');
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');
        $id = $request->input('id');

        $query = Gallery::query()
        ->select([
            'galleries.id',
            'galleries.title',
            'galleries.image',
            DB::raw('CONCAT("' . config('app.url') . '", "/storage/", galleries.image) AS galleryimage_path')
        ])
        ->with('main_image')
        ->with('images');

        if ($id) {
            $query->where('id', $id);
        }

        if ($searchQuery) {
            $query->where('title', 'LIKE', "%{$searchQuery}%");
        }

        $query->orderBy($sortField, $sortDirection);
        $galleries = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'current_page' => $galleries->currentPage(),
            'data' => $galleries->items(),
            'first_page_url' => $galleries->url(1),
            'from' => $galleries->firstItem(),
            'last_page' => $galleries->lastPage(),
            'last_page_url' => $galleries->url($galleries->lastPage()),
            'links' => $galleries->links(),
            'next_page_url' => $galleries->nextPageUrl(),
            'path' => $galleries->path(),
            'per_page' => $galleries->perPage(),
            'prev_page_url' => $galleries->previousPageUrl(),
            'to' => $galleries->lastItem(),
            'total' => $galleries->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'image' => 'nullable|string|max:255',
                'image_id' => 'integer|exists:images,id',
            ]);

            $gallery = Gallery::create($validated);

            return response()->json($gallery, 201);

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
            $gallery = Gallery::findOrFail($id);
            return response()->json($gallery);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $gallery = Gallery::findOrFail($id);

            $validated = $request->validate([
                'title' => 'string|max:255',
                'image' => 'nullable|string|max:255',
                'image_id' => 'integer|exists:images,id',
            ]);

            $gallery->update($validated);

            return response()->json([
                'success' => true,
                'data' => $gallery,
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
            $gallery = Gallery::findOrFail($id);
            $gallery->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function uploadImage(Request $request, $id, $folder = 'galleries')
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

}
