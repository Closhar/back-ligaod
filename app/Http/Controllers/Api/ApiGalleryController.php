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

        // Проверяем, есть ли файлы для загрузки
        if (!$request->hasFile('image') && !$request->hasFile('images')) {
            return response()->json([
                'success' => false,
                'message' => 'No images provided'
            ], 422);
        }

        $uploadedImages = [];
        $errors = [];

        // Получаем файлы (один или несколько)
        $files = [];
        if ($request->hasFile('image')) {
            $files[] = $request->file('image');
        }
        if ($request->hasFile('images')) {
            $files = array_merge($files, $request->file('images'));
        }

        // Валидируем каждый файл
        foreach ($files as $index => $file) {
            $validator = Validator::make(['image' => $file], [
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp',
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'errors' => $validator->errors()->first()
                ];
                continue;
            }

            try {
                $uploadedImage = $this->processAndSaveImage($file, $gallery);
                $uploadedImages[] = $uploadedImage;
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
            }
        }

        // Формируем ответ
        $response = [
            'success' => count($uploadedImages) > 0,
            'message' => $this->generateUploadMessage(count($uploadedImages), count($errors)),
            'uploaded' => $uploadedImages,
        ];

        if (count($errors) > 0) {
            $response['errors'] = $errors;
        }

        $statusCode = count($uploadedImages) > 0 ? 201 : 422;
        return response()->json($response, $statusCode);
    }

    /**
     * Обработать и сохранить изображение
     */
    private function processAndSaveImage($file, $gallery): array
    {
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

        return [
            'id' => $imageModel->id,
            'title' => $imageModel->title,
            'image' => $imagePath,
            'thumbnail' => Storage::disk('public')->url($thumbnailPath),
            'gallery_image_path' => Storage::disk('public')->url($imagePath),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Генерировать сообщение о результатах загрузки
     */
    private function generateUploadMessage(int $uploadedCount, int $errorCount): string
    {
        if ($uploadedCount === 0 && $errorCount === 0) {
            return 'No files were processed';
        }

        if ($uploadedCount === 0) {
            return "Failed to upload {$errorCount} file(s)";
        }

        if ($errorCount === 0) {
            return "Successfully uploaded {$uploadedCount} file(s)";
        }

        return "Successfully uploaded {$uploadedCount} file(s), {$errorCount} failed";
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