<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image as ImageIntervention;

class ApiGalleryController extends Controller
{
    /**
     * Получить список всех галерей
     */
    public function index(): JsonResponse
    {
        $galleries = Gallery::with(['images' => function ($query) {
            $query->orderBy('position', 'asc');
        }])->get();

        return response()->json($galleries);
    }

    /**
     * Создать новую галерею
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $gallery = Gallery::create([
            'title' => $request->title,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gallery created successfully',
            'data' => $gallery
        ], 201);
    }

    /**
     * Получить галерею с изображениями
     */
    public function show($id): JsonResponse
    {
        $gallery = Gallery::with(['images' => function ($query) {
            $query->orderBy('position', 'asc');
        }])->find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        return response()->json($gallery);
    }

    /**
     * Обновить галерею
     */
    public function update(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $gallery->update($request->only(['title']));

        return response()->json([
            'success' => true,
            'message' => 'Gallery updated successfully',
            'data' => $gallery
        ]);
    }

    /**
     * Удалить галерею
     */
    public function destroy($id): JsonResponse
    {
        $gallery = Gallery::with('images')->find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        // Удаляем все изображения галереи
        foreach ($gallery->images as $image) {
            $this->deleteImageFiles($image);
        }

        $gallery->images()->delete();
        $gallery->delete();

        return response()->json([
            'success' => true,
            'message' => 'Gallery deleted successfully'
        ]);
    }

    /**
     * Загрузить изображение в галерею
     */
    public function uploadImage(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('image');
            $fileName = Str::random(32) . '.' . $file->getClientOriginalExtension();

            // Создаем папку для галереи если её нет
            $galleryPath = "galleries/{$gallery->id}";
            if (!Storage::disk('public')->exists($galleryPath)) {
                Storage::disk('public')->makeDirectory($galleryPath);
            }

            // Обрабатываем изображение
            $image = ImageIntervention::make($file);

            // Изменяем размер до 1600px по ширине, сохраняя пропорции
            $image->resize(1600, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Сохраняем основное изображение
            $imagePath = "{$galleryPath}/{$fileName}";
            $image->save(storage_path("app/public/{$imagePath}"), 80, 'jpeg');

            // Создаем thumbnail (50px высота)
            $thumbnail = ImageIntervention::make($file);
            $thumbnail->resize(null, 50, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $thumbnailPath = "{$galleryPath}/thmb_{$fileName}";
            $thumbnail->save(storage_path("app/public/{$thumbnailPath}"), 80, 'jpeg');

            // Сохраняем информацию в базу данных
            $imageModel = Image::create([
                'title' => null,
                'image' => $imagePath,
                'gallery_id' => $gallery->id,
                'position' => Image::where('gallery_id', $gallery->id)->max('position') + 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'id' => $imageModel->id,
                    'title' => $imageModel->title,
                    'image' => $imagePath,
                    'thumbnail' => Storage::disk('public')->url($thumbnailPath),
                    'gallery_image_path' => Storage::disk('public')->url($imagePath),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить изображение (название)
     */
    public function updateImage(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image_id' => 'required|integer|exists:images,id',
            'title' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $image = Image::where('id', $request->image_id)
                     ->where('gallery_id', $gallery->id)
                     ->first();

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found in this gallery'
            ], 404);
        }

        $image->update([
            'title' => $request->title
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Image updated successfully',
            'data' => $image
        ]);
    }

    /**
     * Удалить изображение из галереи
     */
    public function deleteImage(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image_id' => 'required|integer|exists:images,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $image = Image::where('id', $request->image_id)
                     ->where('gallery_id', $gallery->id)
                     ->first();

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found in this gallery'
            ], 404);
        }

        // Удаляем файлы изображения
        $this->deleteImageFiles($image);

        // Удаляем запись из базы данных
        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);
    }

    /**
     * Удалить файлы изображения
     */
    private function deleteImageFiles(Image $image): void
    {
        try {
            // Удаляем основное изображение
            if ($image->image && Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }

            // Удаляем thumbnail
            $thumbnailPath = str_replace('.', '/thmb_', $image->image);
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \Log::error('Error deleting image files: ' . $e->getMessage());
        }
    }
}