<?php

use App\Http\Controllers\Admin\Data\AdminPageController;
use App\Http\Controllers\Admin\Data\ClubController;
use App\Http\Controllers\Admin\Data\CompetitionController;
use App\Http\Controllers\Admin\Data\CityController;
use App\Http\Controllers\Admin\Data\EventController;
use App\Http\Controllers\Admin\Data\EventStreamController;
use App\Http\Controllers\Admin\Data\SportController;
use App\Http\Controllers\Admin\Data\StreamController;
use App\Http\Controllers\Admin\Data\SportPropertyController;
use App\Http\Controllers\Api\ApiAgeController;
use App\Http\Controllers\Api\ApiArenaController;
use App\Http\Controllers\Api\ApiArticleController;
use App\Http\Controllers\Api\ApiCityController;
use App\Http\Controllers\Api\ApiClubController;
use App\Http\Controllers\Api\ApiCompetitionController;
use App\Http\Controllers\Api\ApiEventController;
use App\Http\Controllers\Api\ApiGalleryController;
use App\Http\Controllers\Api\ApiGenderController;
use App\Http\Controllers\Api\ApiParamsController;
use App\Http\Controllers\Api\ApiSportController;
use App\Http\Controllers\Api\ApiSportPropertyController;
use App\Http\Controllers\Api\GalleryAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::group(['prefix' => '/', 'namespace' => 'Api'], function () {
    Route::post('/store', [GalleryAdminController::class, 'show']);
    Route::get('/gallery/{id}', [GalleryAdminController::class, 'gallery']);
    Route::post('/gallery/{id}/rename-title', [GalleryAdminController::class, 'rename']);
    Route::post('/gallery/{id}/delete', [GalleryAdminController::class, 'delete']);
    Route::post('/gallery/{id}/move', [GalleryAdminController::class, 'move']);
});

Route::group(['prefix' => '/v1', 'namespace' => 'Api'], function () {
    Route::get('/genders', [ApiGenderController::class, 'index'])->name('genders.index');
    Route::get('/genders/{id}', [ApiGenderController::class, 'show'])->name('genders.show');
    Route::get('/ages', [ApiAgeController::class, 'index'])->name('ages.index');
    Route::get('/ages/{id}', [ApiAgeController::class, 'show'])->name('ages.show');
    Route::get('/sport_properties', [ApiSportPropertyController::class, 'index'])->name('sport_properties.index');
    Route::get('/sport_properties/{id}', [ApiSportPropertyController::class, 'show'])->name('sport_properties.show');
    Route::get('/sports', [ApiSportController::class, 'index'])->name('sports.index');
    Route::get('/sports/{id}', [ApiSportController::class, 'show'])->name('sports.show');
    Route::get('/cities', [ApiCityController::class, 'index'])->name('cities.index');
    Route::get('/cities/{id}', [ApiCityController::class, 'show'])->name('cities.show');
    Route::get('/clubs', [ApiClubController::class, 'index'])->name('clubs.table');
    Route::get('/clubs/{id}', [ApiClubController::class, 'show'])->name('clubs.item');
    Route::get('/arenas', [ApiArenaController::class, 'index'])->name('arenas.index');
    Route::get('/arenas/{id}', [ApiArenaController::class, 'show'])->name('arenas.show');
    Route::get('/competitions', [ApiCompetitionController::class, 'index'])->name('competitions.table');
    Route::get('/competitions/{id}', [ApiCompetitionController::class, 'show'])->name('competitions.item');
    Route::get('/events', [ApiEventController::class, 'index'])->name('events.table');
    Route::get('/events/{id}', [ApiEventController::class, 'show'])->name('events.item');
    Route::get('/articles', [ApiArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/{id}', [ApiArticleController::class, 'show'])->name('articles.show');
    Route::get('/galleries', [ApiGalleryController::class, 'index'])->name('galleries.index');
    Route::get('/galleries/{id}', [ApiGalleryController::class, 'show'])->name('galleries.show');
    Route::get('/params', [ApiParamsController::class, 'index'])->name('params.show');
    Route::get('/filters', [ApiParamsController::class, 'getTitle'])->name('params.filters');
    Route::get('/page/{id}', [ApiParamsController::class, 'getPage'])->name('params.page');
    Route::get('/apage/{id}', [ApiParamsController::class, 'getAdminPage'])->name('params.apage');
    Route::get('/amenu', [ApiParamsController::class, 'getAdminMenu'])->name('params.amenu');
});

// Аутентификация
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
// Восстановление пароля
Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);

// Управление пользователем
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/chp', [UserController::class, 'changePassword']);
    Route::post('/chp', [UserController::class, 'changePassword']);
    Route::get('/user', [UserController::class, 'show']); // Получить данные пользователя
    Route::post('/user', [UserController::class, 'update']);
    Route::delete('/user/avatar', [UserController::class, 'deleteAvatar']);
});

// Подтверждение email
Route::get('/email/verify/{id}/{hash}', function (Request $request) {

    //$request->fulfill();

    // Перенаправление на страницу Nuxt
    $redirectUrl = env('NUXT_URL') . '/verify-email?' . http_build_query([
            'id' => $request->route('id'),
            'hash' => $request->route('hash'),
            'expires' => $request->query('expires'),
            'signature' => $request->query('signature'),
        ]);

    return redirect($redirectUrl);
})->middleware('signed')->name('api.verification.verify');

Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
    ->middleware(['auth:sanctum', 'throttle:6,1']);
Route::get('/captcha', [AuthController::class, 'generateCaptcha']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);


// АДМИНКА
Route::post('/adminlogin', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'login']);
// Защищенные маршруты (требуют авторизации)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/adminlogout', [AuthController::class, 'logout']);

    //Route::middleware('can:admin')->group(function () {
//    Route::apiResource('admin-pages', AdminPageController::class);
    //});
});
Route::apiResource('admin-pages', AdminPageController::class);

Route::apiResource('events', EventController::class);
Route::get('events/{id}/check-field', [EventController::class, 'checkField']);
Route::post('events/{id}/check-freshness', [EventController::class, 'checkFieldFreshness']);
Route::post('events/{id}/upload-image', [EventController::class, 'uploadImage']);
Route::delete('events/{id}/image', [EventController::class, 'destroyImage']);
Route::post('events/{id}/delete-image', [EventController::class, 'deleteImage']);

Route::apiResource('sports', SportController::class);
Route::post('sports/{id}/upload-image', [SportController::class, 'uploadImage']);
Route::delete('sports/{id}/image', [SportController::class, 'destroyImage']);
Route::post('sports/{id}/delete-image', [SportController::class, 'deleteImage']);

Route::apiResource('competitions', CompetitionController::class);
Route::apiResource('cities', CityController::class);
Route::apiResource('sport_properties', SportPropertyController::class);
Route::apiResource('regions', \App\Http\Controllers\Admin\Data\RegionController::class);
Route::apiResource('series', \App\Http\Controllers\Admin\Data\SeriesController::class);

Route::get('events/{event}/streams', [EventStreamController::class, 'index']);
Route::post('events/{event}/streams', [EventStreamController::class, 'store']);
Route::post('relations/detach', [EventStreamController::class, 'detach']);

Route::apiResource('streams', StreamController::class);

Route::apiResource('clubs', ClubController::class);
Route::post('clubs/{id}/upload-image', [ClubController::class, 'uploadImage']);
Route::delete('clubs/{id}/image', [ClubController::class, 'destroyImage']);
Route::post('clubs/{id}/delete-image', [ClubController::class, 'deleteImage']);

Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent();
})->middleware('web'); // Важно использовать web middleware

Route::post('/upload-image', function(Request $request) {
    $validator = Validator::make($request->all(), [
        'image' => [
            'required',
            'image',
            'mimes:jpeg,png,jpg,gif,webp', // Явное указание разрешенных типов
            'max:6144', // ~6MB
            'dimensions:max_width=3840,max_height=2160' // 4K макс. разрешение
        ]
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

        // Генерация уникального имени файла
        //$fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $fileName = $file;

        // Сохранение с указанием явного пути
        //$path = $file->storeAs('images/' . date('Y/m'), $fileName);
        $path = $request->file('image')->store('images/' . date('Y/m'), 'public');

        // Генерация URL без использования asset() для API
        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'file' => [
                'url' => $url,
                'path' => $path,
                'name' => $fileName,
                'size' => Storage::size($path),
                'mime' => Storage::mimeType($path)
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'File upload failed',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
});
    //->middleware('auth:sanctum');

