<?php

use App\Http\Controllers\Admin\Data\AdminPageController;
use App\Http\Controllers\Admin\Data\ArenaController;
use App\Http\Controllers\Admin\Data\ArticleController;
use App\Http\Controllers\Admin\Data\ClubController;
use App\Http\Controllers\Admin\Data\CompetitionController;
use App\Http\Controllers\Admin\Data\CityController;
use App\Http\Controllers\Admin\Data\EventController;
use App\Http\Controllers\Admin\Data\EventStreamController;
use App\Http\Controllers\Admin\Data\GalleryController;
use App\Http\Controllers\Admin\Data\GenderController;
use App\Http\Controllers\Admin\Data\ImageController;
use App\Http\Controllers\Admin\Data\SportController;
use App\Http\Controllers\Admin\Data\StreamController;
use App\Http\Controllers\Admin\Data\SportPropertyController;
use App\Http\Controllers\Admin\Data\StreamHintController;
use App\Http\Controllers\Admin\Data\VideoController;
use App\Http\Controllers\Api\ApiAgeController;
use App\Http\Controllers\Api\ApiArenaController;
use App\Http\Controllers\Api\ApiArticleController;
use App\Http\Controllers\Api\ApiCityController;
use App\Http\Controllers\Api\ApiClubController;
use App\Http\Controllers\Api\ApiCompetitionController;
use App\Http\Controllers\Api\ApiEventController;
use App\Http\Controllers\Api\ApiGalleryController;
use App\Http\Controllers\Api\ApiGenderController;
use App\Http\Controllers\Api\ApiMenuSectionController;
use App\Http\Controllers\Api\ApiParamsController;
use App\Http\Controllers\Api\ApiSportController;
use App\Http\Controllers\Api\ApiSportPropertyController;
use App\Http\Controllers\Api\ArticleViewController;
use App\Http\Controllers\Api\GalleryAdminController;
use App\Http\Controllers\ParseTableController;
use App\Http\Controllers\PromptTemplateController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\TelegramParseChannelController;
use App\Http\Controllers\Api\TelegramMessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

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
    Route::get('/genders', [ApiGenderController::class, 'index'])->name('api.genders.index');
    Route::get('/genders/{id}', [ApiGenderController::class, 'show'])->name('api.genders.show');
    Route::get('/ages', [ApiAgeController::class, 'index'])->name('api.ages.index');
    Route::get('/ages/{id}', [ApiAgeController::class, 'show'])->name('api.ages.show');
    Route::get('/sport_properties', [ApiSportPropertyController::class, 'index'])->name('api.sport_properties.index');
    Route::get('/sport_properties/{id}', [ApiSportPropertyController::class, 'show'])->name('api.sport_properties.show');
    Route::get('/sports', [ApiSportController::class, 'index'])->name('api.sports.index');
    Route::get('/sports/{id}', [ApiSportController::class, 'show'])->name('api.sports.show');
    Route::get('/cities', [ApiCityController::class, 'index'])->name('api.cities.index');
    Route::get('/cities/{id}', [ApiCityController::class, 'show'])->name('api.cities.show');
    Route::get('/clubs', [ApiClubController::class, 'index'])->name('api.clubs.index');
    Route::get('/clubs/{id}', [ApiClubController::class, 'show'])->name('api.clubs.show');
    Route::get('/arenas', [ApiArenaController::class, 'index'])->name('api.arenas.index');
    Route::get('/arenas/{id}', [ApiArenaController::class, 'show'])->name('api.arenas.show');
    Route::get('/competitions', [ApiCompetitionController::class, 'index'])->name('api.competitions.index');
    Route::get('/competitions/{id}', [ApiCompetitionController::class, 'show'])->name('api.competitions.show');
    Route::get('/events', [ApiEventController::class, 'index'])->name('api.events.index');
    Route::get('/events/{id}', [ApiEventController::class, 'show'])->name('api.events.show');
    Route::get('/articles', [ApiArticleController::class, 'index'])->name('api.articles.index');
    Route::get('/articles/{id}', [ApiArticleController::class, 'show'])->name('api.articles.show');
    Route::get('/galleries', [ApiGalleryController::class, 'index'])->name('api.galleries.index');
    Route::get('/galleries/{id}', [ApiGalleryController::class, 'show'])->name('api.galleries.show');
    Route::get('/params', [ApiParamsController::class, 'index'])->name('params.show');
    Route::get('/filters', [ApiParamsController::class, 'getTitle'])->name('params.filters');
    Route::get('/page/{id}', [ApiParamsController::class, 'getPage'])->name('params.page');
    Route::get('/apage/{id}', [ApiParamsController::class, 'getAdminPage'])->name('params.apage');
    Route::get('/amenu', [ApiParamsController::class, 'getAdminMenu'])->name('params.amenu');

    // Маршруты для загрузки изображений в галереи
    // Route::post('/galleries/{id}/upload-image', [ApiGalleryController::class, 'uploadImage']);
    // Route::delete('/galleries/{id}/image', [ApiGalleryController::class, 'destroyImage']);
    // Route::post('/galleries/{id}/delete-image', [ApiGalleryController::class, 'deleteImage']);
    // Route::post('/galleries/{id}/delete-multiple-images', [ApiGalleryController::class, 'deleteMultipleImages']);

        // Маршруты для галерей
        Route::prefix('galleries')->group(function () {
            Route::get('/', [ApiGalleryController::class, 'index']);
            Route::post('/', [ApiGalleryController::class, 'store']);
            Route::get('/{id}', [ApiGalleryController::class, 'show']);
            Route::put('/{id}', [ApiGalleryController::class, 'update']);
            Route::patch('/{id}', [ApiGalleryController::class, 'update']);
            Route::delete('/{id}', [ApiGalleryController::class, 'destroy']);
            Route::post('/{id}/upload-image', [ApiGalleryController::class, 'uploadImage']);
            Route::post('/{id}/image', [ApiGalleryController::class, 'updateImage']);
            Route::post('/{id}/delete-image', [ApiGalleryController::class, 'deleteImage']);
            Route::post('/{id}/delete-multiple-images', [ApiGalleryController::class, 'deleteMultipleImages']);
            Route::post('/{id}/update-positions', [ApiGalleryController::class, 'updatePositions']);
            Route::post('/{id}/download-images', [ApiGalleryController::class, 'downloadImages']);

        });

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

// Публичные маршруты
Route::post('/ai/upload-file', [App\Http\Controllers\Admin\Data\AIController::class, 'uploadFile']);
Route::post('/ai/generate', [App\Http\Controllers\Admin\Data\AIController::class, 'generate']);

// Защищенные маршруты (требуют авторизации)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/adminlogout', [AuthController::class, 'logout']);
    //Route::middleware('can:admin')->group(function () {
//    Route::apiResource('admin-pages', AdminPageController::class);
    //});
});
//Route::apiResource('admin-pages', AdminPageController::class);

Route::apiResource('admin-pages', AdminPageController::class);

// Маршруты для управления разделами меню
Route::apiResource('menu-sections', ApiMenuSectionController::class);
Route::get('/menu-sections/select', [ApiMenuSectionController::class, 'getForSelect']);

// Маршруты для работы с телеграм
Route::prefix('telegram')->group(function () {
    // Базовые маршруты
    Route::apiResource('channels', TelegramController::class);
    Route::post('/send', [TelegramController::class, 'sendMessage']);

    // Маршруты для тестирования
    Route::get('/test-messages', [TelegramParseChannelController::class, 'testMessages']);

    // Маршруты для управления каналами парсинга
    Route::prefix('parse-channels')->group(function () {
        Route::get('/', [TelegramParseChannelController::class, 'index']);
        Route::post('/', [TelegramParseChannelController::class, 'store']);
        Route::get('/{id}', [TelegramParseChannelController::class, 'show']);
        Route::put('/{id}', [TelegramParseChannelController::class, 'update']);
        Route::delete('/{id}', [TelegramParseChannelController::class, 'destroy']);
        Route::get('/{id}/check', [TelegramParseChannelController::class, 'checkChannel']);
        Route::get('/{id}/stats', [TelegramParseChannelController::class, 'stats']);
    });
});


// Маршруты для счетчика просмотров статей
Route::prefix('v1/article_count')->group(function () {
    // Тестовый маршрут для диагностики
    Route::get('{slug}/test', [ArticleViewController::class, 'test']);

    // Временный GET маршрут для записи просмотра (для диагностики)
    Route::get('{slug}/views-record', [ArticleViewController::class, 'recordViewSimple']);

    // Упрощенная запись просмотра для диагностики
    Route::post('{slug}/views-simple', [ArticleViewController::class, 'recordViewSimple']);

    // Записать просмотр статьи
    Route::post('{slug}/views', [ArticleViewController::class, 'recordView']);

    // Получить количество просмотров статьи
    Route::get('{slug}/views', [ArticleViewController::class, 'getViewsCount']);

    // Получить статистику просмотров статьи
    Route::get('{slug}/views/stats', [ArticleViewController::class, 'getViewsStats']);
});

// Публичные маршруты для получения сообщений из Telegram
Route::prefix('telegram/messages')->group(function () {
    Route::get('/fetch', [TelegramMessageController::class, 'fetchMessages']);
    Route::post('/fetch', [TelegramMessageController::class, 'fetchMessages']);
});

Route::prefix('prompt-templates')->group(function () {
    Route::get('/', [PromptTemplateController::class, 'index']);
    Route::post('/', [PromptTemplateController::class, 'store']);
    Route::put('/{template}', [PromptTemplateController::class, 'update']);
    Route::delete('/{template}', [PromptTemplateController::class, 'destroy']);
});

Route::match(['get', 'post'], '/vk/video-preview', function (Request $request) {
    $ownerId = $request->input('ownerId');
    $videoId = $request->input('videoId');
    $vkToken = $request->input('vkToken');

    $apiUrl = "https://api.vk.com/method/video.get?videos={$ownerId}_{$videoId}&access_token={$vkToken}&v=5.131";

    $response = Http::get($apiUrl);

    return $response->json();
});

Route::match(['get', 'post'], '/rutube/video-preview', function (Request $request) {
    $videoId = $request->input('videoId');

    $response = Http::get("https://rutube.ru/api/video/{$videoId}/");

    return $response->json();
});

Route::match(['get', 'post'], '/image-proxy', function (Request $request) {
    $url = $request->input('url');

    $response = Http::get($url);

    return response($response->body())
        ->header('Content-Type', $response->header('Content-Type'));
});

// Тестовый маршрут для авторизации Telegram
Route::match(['get', 'post'], '/telegram/test-auth', [TelegramParseChannelController::class, 'testAuth']);

// Парсинг
Route::post('/parse-tables/parse', [ParseTableController::class, 'parse']);
Route::post('/parse-tables/reparse', [ParseTableController::class, 'reparse']);

Route::apiResource('parse-tables', \App\Http\Controllers\Admin\Data\ParseTableController::class);
Route::apiResource('parse-table-contents', \App\Http\Controllers\Admin\Data\ParseTableContentController::class);

Route::apiResource('events', EventController::class);
Route::get('events/{id}/check-field', [EventController::class, 'checkField']);
Route::get('events/{id}/check-freshness', [EventController::class, 'checkFieldFreshness']);
Route::post('events/{id}/upload-image', [EventController::class, 'uploadImage']);
Route::delete('events/{id}/image', [EventController::class, 'destroyImage']);
Route::post('events/{id}/delete-image', [EventController::class, 'deleteImage']);

Route::apiResource('sports', SportController::class);
Route::post('sports/{id}/upload-image', [SportController::class, 'uploadImage']);
Route::delete('sports/{id}/image', [SportController::class, 'destroyImage']);
Route::post('sports/{id}/delete-image', [SportController::class, 'deleteImage']);

Route::apiResource('competitions', CompetitionController::class);
Route::post('competitions/{id}/upload-image', [CompetitionController::class, 'uploadImage']);
Route::delete('competitions/{id}/image', [CompetitionController::class, 'destroyImage']);
Route::post('competitions/{id}/delete-image', [CompetitionController::class, 'deleteImage']);

Route::apiResource('images', ImageController::class);
Route::post('images/{id}/upload-image', [ImageController::class, 'uploadImage']);
Route::delete('images/{id}/image', [ImageController::class, 'destroyImage']);
Route::post('images/{id}/delete-image', [ImageController::class, 'deleteImage']);


Route::apiResource('cities', CityController::class);
Route::apiResource('sport_properties', SportPropertyController::class);
Route::apiResource('regions', \App\Http\Controllers\Admin\Data\RegionController::class);
Route::apiResource('series', \App\Http\Controllers\Admin\Data\SeriesController::class);
Route::apiResource('series-types', \App\Http\Controllers\Admin\Data\SeriesTypeController::class);

Route::get('events/{event}/streams', [EventStreamController::class, 'index']);
Route::post('events/{event}/streams', [EventStreamController::class, 'store']);
Route::post('events/{event}/swap-fields', [EventController::class, 'swapFields']);
Route::post('relations/detach', [EventStreamController::class, 'detach']);

Route::get('events/{id}/rel-value', [EventController::class, 'getRelValue']);
Route::post('events/{id}/rel-value', [EventController::class, 'updateRelValue']);
Route::post('events/bulk-delete', [EventController::class, 'bulkDelete'])->name('events.bulk-delete');


Route::apiResource('streams', StreamController::class);
Route::apiResource('stream-hints', StreamHintController::class);
Route::apiResource('genders', GenderController::class);
Route::apiResource('arenas', ArenaController::class);
Route::post('arenas/{id}/upload-image', [ArenaController::class, 'uploadImage']);
Route::delete('arenas/{id}/image', [ArenaController::class, 'destroyImage']);
Route::post('arenas/{id}/delete-image', [ArenaController::class, 'deleteImage']);

Route::apiResource('articles', ArticleController::class);
Route::post('articles/{id}/upload-image', [ArticleController::class, 'uploadImage']);
Route::delete('articles/{id}/image', [ArticleController::class, 'destroyImage']);
Route::post('articles/{id}/delete-image', [ArticleController::class, 'deleteImage']);

// Маршрут для сохранения morphedByMany отношений
Route::post('/relations/save', [App\Http\Controllers\Api\ApiRelationsController::class, 'saveRelations']);

// Маршрут для получения связанных записей
Route::get('/relations/get', [App\Http\Controllers\Api\ApiRelationsController::class, 'getRelations']);

// Маршрут для получения доступных записей для связи
Route::get('/relations/available', [App\Http\Controllers\Api\ApiRelationsController::class, 'getAvailableRecords']);

// Маршрут для сохранения отношений статей
Route::post('articles/{id}/relations', function (Request $request, $id) {
    $article = \App\Models\Article::find($id);
    if (!$article) {
        return response()->json(['message' => 'Статья не найдена'], 404);
    }

    $relationType = $request->relation_type;
    $relationIds = $request->relation_ids;

    // Обновляем отношения в зависимости от типа
    switch ($relationType) {
        case 'sports':
            $article->sports()->sync($relationIds);
            break;
        case 'clubs':
            $article->clubs()->sync($relationIds);
            break;
        case 'arenas':
            $article->arenas()->sync($relationIds);
            break;
        case 'competitions':
            $article->competitions()->sync($relationIds);
            break;
        case 'events':
            $article->events()->sync($relationIds);
            break;
        case 'galleries':
            $article->galleries()->sync($relationIds);
            break;
        case 'videos':
            $article->videos()->sync($relationIds);
            break;
        default:
            return response()->json(['message' => 'Неизвестный тип отношения'], 400);
    }

    return response()->json(['message' => 'Отношения успешно обновлены']);
});

Route::apiResource('galleries', GalleryController::class);
// Route::post('galleries/{id}/upload-image', [GalleryController::class, 'uploadImage']);
Route::delete('galleries/{id}/image', [GalleryController::class, 'destroyImage']);
// Route::post('galleries/{id}/delete-image', [GalleryController::class, 'deleteImage']);

Route::apiResource('videos', VideoController::class);

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
        $path = $request->file('image')->store('images/' . date('Y/m'), 'public');
        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'file' => [
                'url' => $url,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
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

Route::prefix('telegram-parse-channels')->group(function () {
    Route::get('/', [TelegramParseChannelController::class, 'index']);
    Route::post('/', [TelegramParseChannelController::class, 'store']);
    Route::get('/{id}', [TelegramParseChannelController::class, 'show']);
    Route::put('/{id}', [TelegramParseChannelController::class, 'update']);
    Route::delete('/{id}', [TelegramParseChannelController::class, 'destroy']);
    Route::get('/{id}/stats', [TelegramParseChannelController::class, 'stats']);
    Route::get('/{id}/check', [TelegramParseChannelController::class, 'checkChannel']);
    Route::post('/test-messages', [TelegramParseChannelController::class, 'testMessages']);
    Route::get('/test-auth', [TelegramParseChannelController::class, 'testAuth']);
});

Route::get('test-telegram', [TelegramParseChannelController::class, 'testMessages']);
