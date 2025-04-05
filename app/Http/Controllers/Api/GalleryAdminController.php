<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GalleryAdminRenameTitleRequest;
use App\Http\Requests\Api\GalleryAdminRequest;
use App\Http\Requests\Api\GalleryAdminSortRequest;
use App\Http\Resources\GalleryAdminResource;
use App\Models\Gallery;
use App\Models\Image;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\ImageDriver;
use Storage;

class GalleryAdminController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function show(GalleryAdminRequest $request): JsonResponse
    {
        $data = $request->validated();
        $images = $data['images'];
        unset($data['images']);

        foreach ($images as $image) {

            $name = md5(Carbon::now() . '_' . $image->getClientOriginalName()) . '.' . $image->getClientOriginalExtension();
            $filePath = Storage::disk('public')->putFileAs('/galleries/' . $data['gallery_id'], $image, $name);

            Image::create([
                'gallery_id' => $data['gallery_id'],
                'image' => $filePath,
            ]);

            \Spatie\Image\Image::useImageDriver(ImageDriver::Gd)->loadFile($image)
                ->fit(Fit::Crop, 400, 225)
                ->save(public_path('storage/galleries/') . $data['gallery_id'] . '/thmb_' . $name);

        }
        return response()->json(['message' => 'success']);
    }

    public function gallery(string $gallery_id): GalleryAdminResource
    {
        $g = Gallery::find($gallery_id);
        return new GalleryAdminResource($g);
    }

    public function rename(GalleryAdminRenameTitleRequest $request, $id): JsonResponse
    {
        // Валидация входных данных
        $data = $request->validated();

        // Находим запись по ID
        $image = Image::find($id);

        // Проверяем, существует ли запись
        if (!$image) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        // Переименовываем поле title
        $image->title = $request->title;
        $image->save();

        // Возвращаем успешный ответ
        return response()->json(['message' => 'Title updated successfully', 'image' => $image]);
    }

    public function delete($id): JsonResponse
    {
        // Находим запись по ID
        $image = Image::find($id);

        // Проверяем, существует ли запись
        if (!$image) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        // Получаем путь к файлу изображения
        $imagePath = $image->image;

        $thumb = preg_replace('~^(.*/)([^/]+)$~', '$1thmb_$2', $imagePath);
        // Удаляем запись из базы данных
        $image->delete();
        // Удаляем файл изображения, если он существует
//        if ($imagePath && Storage::exists($imagePath)) {
        Storage::disk('public')->delete($imagePath);
        Storage::disk('public')->delete($thumb);
//        }

        // Возвращаем успешный ответ
        return response()->json(['message' => 'Image deleted successfully']);
    }

    public function move(GalleryAdminSortRequest $request, $image_id): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();

        $image = Image::find($image_id);

        $image->update([
            'position' => round((float)$data['position'], 5)
        ]);

        return redirect()->back();
    }

}
