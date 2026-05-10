<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ZipArchive;

class ApiGalleryController extends Controller
{
    /**
     * Получить список всех галерей
     */
    public function index(Request $request): JsonResponse
    {
        $query = Gallery::query()
            ->select(['id', 'title', 'image', 'image_id', 'created_at', 'updated_at'])
            ->withCount('images');

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where('title', 'LIKE', "%{$search}%");
        }

        if ($request->boolean('include_images')) {
            $query->with('images');
        }

        $query->orderByDesc('id');

        if ($request->has('per_page')) {
            return response()->json($query->paginate((int) $request->input('per_page', 50)));
        }

        $limit = (int) $request->input('limit', 0);
        if ($limit > 0) {
            $query->limit(min($limit, 1000));
        }

        $galleries = $query->get();

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
                'errors' => $validator->errors(),
            ], 422);
        }

        $gallery = Gallery::create([
            'title' => $request->title,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gallery created successfully',
            'data' => $gallery,
        ], 201);
    }

    /**
     * Получить галерею с изображениями
     */
    public function show($id): JsonResponse
    {
        $gallery = Gallery::with('images')->find($id);

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
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

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $gallery->update($request->only(['title']));

        return response()->json([
            'success' => true,
            'message' => 'Gallery updated successfully',
            'data' => $gallery,
        ]);
    }

    /**
     * Удалить галерею
     */
    public function destroy($id): JsonResponse
    {
        $gallery = Gallery::with('images')->find($id);

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
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
            'message' => 'Gallery deleted successfully',
        ]);
    }

    /**
     * Загрузить изображение в галерею
     */
    public function uploadImage(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
            ], 404);
        }

        // Проверяем, есть ли файлы для загрузки
        if (! $request->hasFile('image') && ! $request->hasFile('images')) {
            return response()->json([
                'success' => false,
                'message' => 'No images provided',
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
                    'errors' => $validator->errors()->first(),
                ];

                continue;
            }

            try {
                $uploadedImage = $this->processAndSaveImage($file, $gallery);
                $uploadedImages[] = $uploadedImage;
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
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
        $fileName = Str::random(32).'.jpg'; // Всегда сохраняем как JPEG

        // Создаем папку для галереи если её нет
        $galleryPath = "galleries/{$gallery->id}";
        if (! Storage::disk('public')->exists($galleryPath)) {
            Storage::disk('public')->makeDirectory($galleryPath);
        }

        // Получаем информацию об изображении
        $imageInfo = getimagesize($file->getPathname());
        if (! $imageInfo) {
            throw new \Exception('Invalid image file');
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $imageType = $imageInfo[2];

        // Загружаем изображение в зависимости от типа
        $sourceImage = $this->loadImage($file->getPathname(), $imageType);
        if (! $sourceImage) {
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

        // Накладываем логотип если включен
        $this->applyLogoToImage($resizedImage, request());

        // Сохраняем основное изображение
        $imagePath = "{$galleryPath}/{$fileName}";
        ob_start();
        $mainSaved = imagejpeg($resizedImage, null, 80);
        $mainContents = ob_get_clean();

        if (! $mainSaved || $mainContents === false) {
            throw new \Exception('Failed to save main image');
        }

        Storage::disk('public')->put($imagePath, $mainContents);

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

        // Накладываем логотип на thumbnail если включен
        $this->applyLogoToImage($thumbnailImage, request(), true);

        $thumbnailPath = "{$galleryPath}/thmb_{$fileName}";

        ob_start();
        $thumbnailSaved = imagejpeg($thumbnailImage, null, 80);
        $thumbnailContents = ob_get_clean();

        if (! $thumbnailSaved || $thumbnailContents === false) {
            throw new \Exception('Failed to save thumbnail');
        }

        Storage::disk('public')->put($thumbnailPath, $thumbnailContents);

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

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $image = Image::where('id', $request->image_id)
            ->where('gallery_id', $gallery->id)
            ->first();

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found in this gallery',
            ], 404);
        }

        $image->update([
            'title' => $request->title,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Image updated successfully',
            'data' => $image,
        ]);
    }

    /**
     * Удалить изображение
     */
    public function deleteImage(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image_id' => 'required|integer|exists:images,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $image = Image::where('id', $request->image_id)
            ->where('gallery_id', $gallery->id)
            ->first();

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found in this gallery',
            ], 404);
        }

        $this->deleteImageFiles($image);
        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
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
        $thumbnailPath = preg_replace('~^(.*/)([^/]+)$~', '$1thmb_$2', (string) $image->image);
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

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
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
                'errors' => $validator->errors(),
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
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        $message = "Successfully deleted {$deletedCount} image(s)";
        if (count($errors) > 0) {
            $message .= ', '.count($errors).' failed';
        }

        return response()->json([
            'success' => $deletedCount > 0,
            'message' => $message,
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Обновить позиции изображений (drag-and-drop)
     */
    public function updatePositions(Request $request, $id): JsonResponse
    {
        $gallery = Gallery::find($id);

        if (! $gallery) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'positions' => 'required|array',
            'positions.*.image_id' => 'required|integer|exists:images,id',
            'positions.*.position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $positions = $request->positions;
        $updatedCount = 0;
        $errors = [];

        // Проверяем, что все изображения принадлежат данной галерее
        $imageIds = collect($positions)->pluck('image_id')->toArray();
        $galleryImages = Image::where('gallery_id', $gallery->id)
            ->whereIn('id', $imageIds)
            ->pluck('id')
            ->toArray();

        if (count($imageIds) !== count($galleryImages)) {
            return response()->json([
                'success' => false,
                'message' => 'Some images do not belong to this gallery',
            ], 422);
        }

        // Обновляем позиции
        foreach ($positions as $positionData) {
            try {
                $image = Image::where('id', $positionData['image_id'])
                    ->where('gallery_id', $gallery->id)
                    ->first();

                if ($image) {
                    $image->update(['position' => $positionData['position']]);
                    $updatedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'image_id' => $positionData['image_id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $message = "Successfully updated positions for {$updatedCount} image(s)";
        if (count($errors) > 0) {
            $message .= ', '.count($errors).' failed';
        }

        return response()->json([
            'success' => $updatedCount > 0,
            'message' => $message,
            'updated_count' => $updatedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Кастомная функция для наложения PNG с альфа-каналом
     */
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
    {
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagealphablending($cut, false);
        imagesavealpha($cut, true);
        $transparent = imagecolorallocatealpha($cut, 255, 255, 255, 127);
        imagefill($cut, 0, 0, $transparent);

        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);

        imagecopy($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h);
        imagedestroy($cut);
    }

    /**
     * Наложить логотип на изображение
     */
    private function applyLogoToImage(&$image, $request, $isThumbnail = false)
    {
        if (! $request->has('logo_enabled') || $request->logo_enabled !== 'true') {
            return;
        }

        try {
            $logoImage = null;
            $tempFile = null;
            if ($request->hasFile('custom_logo')) {
                $logoFile = $request->file('custom_logo');
                $logoInfo = getimagesize($logoFile->getPathname());
                if ($logoInfo) {
                    $logoImage = $this->loadImage($logoFile->getPathname(), $logoInfo[2]);
                }
            } elseif ($request->has('default_logo_url') && ! empty($request->default_logo_url)) {
                $logoUrl = $request->default_logo_url;
                if (filter_var($logoUrl, FILTER_VALIDATE_URL)) {
                    $tempFile = tempnam(sys_get_temp_dir(), 'logo_');
                    $logoContent = file_get_contents($logoUrl);
                    if ($logoContent !== false) {
                        file_put_contents($tempFile, $logoContent);
                        $logoInfo = getimagesize($tempFile);
                        if ($logoInfo) {
                            $logoImage = $this->loadImage($tempFile, $logoInfo[2]);
                        }
                    }
                } else {
                    $logoUrl = storage_path("app/public/{$logoUrl}");
                    if (file_exists($logoUrl)) {
                        $logoInfo = getimagesize($logoUrl);
                        if ($logoInfo) {
                            $logoImage = $this->loadImage($logoUrl, $logoInfo[2]);
                        }
                    }
                }
            }
            if (! $logoImage) {
                return;
            }
            $position = $request->get('logo_position', 'bottom-right');
            $size = (int) $request->get('logo_size', 15);
            $opacity = (float) $request->get('logo_opacity', 0.8);
            $imageWidth = imagesx($image);
            $imageHeight = imagesy($image);
            $logoWidth = ($imageWidth * $size) / 100;
            $logoHeight = ($logoWidth * imagesy($logoImage)) / imagesx($logoImage);
            if ($isThumbnail) {
                $logoWidth = min($logoWidth, 30);
                $logoHeight = ($logoWidth * imagesy($logoImage)) / imagesx($logoImage);
            }
            $resizedLogo = imagecreatetruecolor($logoWidth, $logoHeight);
            imagealphablending($resizedLogo, false);
            imagesavealpha($resizedLogo, true);
            $transparent = imagecolorallocatealpha($resizedLogo, 255, 255, 255, 127);
            imagefill($resizedLogo, 0, 0, $transparent);
            imagecopyresampled($resizedLogo, $logoImage, 0, 0, 0, 0, $logoWidth, $logoHeight, imagesx($logoImage), imagesy($logoImage));
            $x = 0;
            $y = 0;
            $padding = 10;
            switch ($position) {
                case 'top-left':
                    $x = $padding;
                    $y = $padding;
                    break;
                case 'top-right':
                    $x = $imageWidth - $logoWidth - $padding;
                    $y = $padding;
                    break;
                case 'bottom-left':
                    $x = $padding;
                    $y = $imageHeight - $logoHeight - $padding;
                    break;
                case 'bottom-right':
                default:
                    $x = $imageWidth - $logoWidth - $padding;
                    $y = $imageHeight - $logoHeight - $padding;
                    break;
            }
            if ($opacity < 1) {
                $this->applyPngOpacity($resizedLogo, $opacity);
            }
            imagecopy($image, $resizedLogo, $x, $y, 0, 0, $logoWidth, $logoHeight);
            imagedestroy($logoImage);
            imagedestroy($resizedLogo);
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        } catch (\Exception $e) {
            if (isset($tempFile) && $tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function applyPngOpacity(&$im, $opacity)
    {
        $w = imagesx($im);
        $h = imagesy($im);

        $opacity = max(0, min(1, $opacity));
        $alpha = 127 * (1 - $opacity);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgba = imagecolorat($im, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;
                $color = imagecolorsforindex($im, $rgba);

                $finalAlpha = min(127, $a + $alpha);

                $newColor = imagecolorallocatealpha($im, $color['red'], $color['green'], $color['blue'], $finalAlpha);
                imagesetpixel($im, $x, $y, $newColor);
            }
        }
    }

    public function downloadImages(Request $request, $galleryId)
    {
        $gallery = Gallery::findOrFail($galleryId);

        $validator = Validator::make($request->all(), [
            'image_ids' => 'required|array',
            'image_ids.*' => 'integer|exists:images,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $images = Image::whereIn('id', $request->image_ids)
            ->where('gallery_id', $gallery->id)
            ->get();

        if ($images->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No images found',
            ], 404);
        }

        $zipFileName = 'gallery_images_'.time().'.zip';
        $zipPath = storage_path('app/tmp/'.$zipFileName);

        if (! file_exists(storage_path('app/tmp'))) {
            mkdir(storage_path('app/tmp'), 0777, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $publicDisk = Storage::disk('public');

            foreach ($images as $img) {
                if ($publicDisk->exists($img->image)) {
                    $zip->addFromString(basename($img->image), $publicDisk->get($img->image));
                }
            }
            $zip->close();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Could not create zip',
            ], 500);
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
