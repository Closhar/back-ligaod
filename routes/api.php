<?php

use App\Http\Controllers\Admin\Data\AdminPageController;
use App\Http\Controllers\Admin\Data\ClubController;
use App\Http\Controllers\Admin\Data\CompetitionController;
use App\Http\Controllers\Admin\Data\EventController;
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
use Illuminate\Support\Facades\Route;

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
Route::apiResource('competitions', CompetitionController::class);
Route::apiResource('clubs', ClubController::class);

Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent();
})->middleware('web'); // Важно использовать web middleware

Route::post('/test-auth', function () {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->user() ? auth()->user()->only('id', 'email', 'is_admin') : null,
        'token_valid' => auth('sanctum')->check()
    ]);
});

