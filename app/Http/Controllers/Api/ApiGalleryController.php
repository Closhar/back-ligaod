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

class ApiGalleryController extends Controller
{
    /**
     * Получить список всех галерей
     */
    public function index(): JsonResponse
    {
        $galleries = Gallery::with('images')->get();

        return response()->json($galleries);
    }

    /**
     * Создать новую галерею
     */
    public function store(Request $request): JsonResponse
    {
        // Проверяем, является ли это обновлением существующей галереи
        if ($request->has('action') && $request->action === 'update' && $request->has('id')) {
            return $this->update($request, $request->id);
        }

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
            'title' => $request->title
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
        $gallery = Gallery::with('images')->find($id);

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
     * Обработать и сохранить изображение (без Intervention Image)
     */
    private function processAndSaveImage($file, $gallery): array
    {
        $fileName = Str::random(32) . '.jpg'; // Всегда сохраняем как JPEG

        // Создаем папку для галереи если её нет
        $galleryPath = "galleries/{$gallery->id}";
        if (!Storage::disk('public')->exists($galleryPath)) {
            Storage::disk('public')->makeDirectory($galleryPath);
        }

        // Получаем информацию об изображении
        $imageInfo = getimagesize($file->getPathname());
        if (!$imageInfo) {
            throw new \Exception('Invalid image file');
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $imageType = $imageInfo[2];

        // Загружаем изображение в зависимости от типа
        $sourceImage = $this->loadImage($file->getPathname(), $imageType);
        if (!$sourceImage) {
            throw new \Exception('Failed to load image');
        }

        // Вычисляем новые размеры (максимум 1600px по ширине)
        $maxWidth = 1600;
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;

        if ($originalWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = round(($originalHeight * $maxWidth) / $originalWidth);
        }

        // Создаем новое изображение
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Сохраняем прозрачность для PNG
        if ($imageType === IMAGETYPE_PNG) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }

        // Изменяем размер
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Сохраняем основное изображение
        $imagePath = "{$galleryPath}/{$fileName}";
        $fullImagePath = storage_path("app/public/{$imagePath}");

        if (!imagejpeg($resizedImage, $fullImagePath, 80)) {
            throw new \Exception('Failed to save main image');
        }

        // Создаем thumbnail (300px ширина)
        $thumbWidth = 300;
        $thumbHeight = round(($newHeight * $thumbWidth) / $newWidth);

        $thumbnailImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // Сохраняем прозрачность для PNG
        if ($imageType === IMAGETYPE_PNG) {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefill($thumbnailImage, 0, 0, $transparent);
        }

        imagecopyresampled($thumbnailImage, $resizedImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $newWidth, $newHeight);

        $thumbnailPath = "{$galleryPath}/thmb_{$fileName}";
        $fullThumbnailPath = storage_path("app/public/{$thumbnailPath}");

        if (!imagejpeg($thumbnailImage, $fullThumbnailPath, 80)) {
            throw new \Exception('Failed to save thumbnail');
        }

        // Освобождаем память
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        imagedestroy($thumbnailImage);

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
     * Загрузить изображение в зависимости от типа
     */
    private function loadImage($path, $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($path);
            default:
                return false;
        }
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

        return "Uploaded {$uploadedCount} file(s), {$errorCount} failed";
    }

    /**
     * Обновить изображение
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
     * Удалить изображение
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

        $this->deleteImageFiles($image);
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
        // Удаляем основное изображение
        if (Storage::disk('public')->exists($image->image)) {
            Storage::disk('public')->delete($image->image);
        }

        // Удаляем thumbnail
        $thumbnailPath = str_replace('.jpg', '', $image->image);
        $thumbnailPath = str_replace('galleries/', 'galleries/thmb_', $thumbnailPath);
        if (Storage::disk('public')->exists($thumbnailPath)) {
            Storage::disk('public')->delete($thumbnailPath);
        }
    }

    /**
     * Массовое удаление изображений
     */
    public function deleteMultipleImages(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image_ids' => 'required|array',
            'image_ids.*' => 'integer|exists:images,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $imageIds = $request->image_ids;
        $deletedCount = 0;
        $errors = [];

        foreach ($imageIds as $imageId) {
            $image = Image::where('id', $imageId)
                ->where('gallery_id', $gallery->id)
                ->first();

            if ($image) {
                try {
                    $this->deleteImageFiles($image);
                    $image->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'image_id' => $imageId,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        $message = "Successfully deleted {$deletedCount} image(s)";
        if (count($errors) > 0) {
            $message .= ", " . count($errors) . " failed";
        }

        return response()->json([
            'success' => $deletedCount > 0,
            'message' => $message,
            'deleted_count' => $deletedCount,
            'errors' => $errors
        ]);
    }

    /**
     * Массовое удаление изображений
     */
    public function deleteMultipleImages(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image_ids' => 'required|array',
            'image_ids.*' => 'integer|exists:images,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $imageIds = $request->image_ids;
        $deletedCount = 0;
        $errors = [];

        foreach ($imageIds as $imageId) {
            $image = Image::where('id', $imageId)
                ->where('gallery_id', $gallery->id)
                ->first();

            if ($image) {
                try {
                    $this->deleteImageFiles($image);
                    $image->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'image_id' => $imageId,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        $message = "Successfully deleted {$deletedCount} image(s)";
        if (count($errors) > 0) {
            $message .= ", " . count($errors) . " failed";
        }

        return response()->json([
            'success' => $deletedCount > 0,
            'message' => $message,
            'deleted_count' => $deletedCount,
            'errors' => $errors
        ]);
    }

    /**
     * Изменить порядок изображений (drag & drop)
     */
    public function reorderImages(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (!$gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'dragged_image_id' => 'required|integer|exists:images,id',
            'target_image_id' => 'required|integer|exists:images,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Получаем изображения
        $draggedImage = Image::where('id', $request->dragged_image_id)
            ->where('gallery_id', $gallery->id)
            ->first();

        $targetImage = Image::where('id', $request->target_image_id)
            ->where('gallery_id', $gallery->id)
            ->first();

        if (!$draggedImage || !$targetImage) {
            return response()->json([
                'success' => false,
                'message' => 'One or both images not found in this gallery'
            ], 404);
        }

        if ($draggedImage->id === $targetImage->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reorder image to itself'
            ], 422);
        }

        try {
            // Получаем все изображения галереи, отсортированные по position
            $images = Image::where('gallery_id', $gallery->id)
                ->orderBy('position')
                ->get();

            $draggedIndex = $images->search(function ($image) use ($draggedImage) {
                return $image->id === $draggedImage->id;
            });

            $targetIndex = $images->search(function ($image) use ($targetImage) {
                return $image->id === $targetImage->id;
            });

            if ($draggedIndex === false || $targetIndex === false) {
                throw new \Exception('Image positions not found');
            }

            // Удаляем перетаскиваемое изображение из массива
            $images->splice($draggedIndex, 1);

            // Вставляем перетаскиваемое изображение на новую позицию
            $images->splice($targetIndex, 0, [$draggedImage]);

            // Обновляем позиции всех изображений
            foreach ($images as $index => $image) {
                $image->update(['position' => $index + 1]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Images reordered successfully',
                'data' => [
                    'dragged_image_id' => $draggedImage->id,
                    'target_image_id' => $targetImage->id,
                    'new_positions' => $images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'position' => $image->position
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder images: ' . $e->getMessage()
            ], 500);
        }
    }

}