<?php

use App\Http\Controllers\ActionTypeController;
use App\Http\Controllers\Admin\Data\AdminPageController;
use App\Http\Controllers\Admin\Data\ArenaController;
use App\Http\Controllers\Admin\Data\ArticleController;
use App\Http\Controllers\Admin\Data\CityController;
use App\Http\Controllers\Admin\Data\ClubController;
use App\Http\Controllers\Admin\Data\CompetitionController;
use App\Http\Controllers\Admin\Data\EventController;
use App\Http\Controllers\Admin\Data\EventStreamController;
use App\Http\Controllers\Admin\Data\GalleryController;
use App\Http\Controllers\Admin\Data\GenderController;
use App\Http\Controllers\Admin\Data\ImageController;
use App\Http\Controllers\Admin\Data\SportController;
use App\Http\Controllers\Admin\Data\SportPropertyController;
use App\Http\Controllers\Admin\Data\StreamController;
use App\Http\Controllers\Admin\Data\StreamHintController;
use App\Http\Controllers\Admin\Data\VideoController;
use App\Http\Controllers\Api\AmpluaController;
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
use App\Http\Controllers\Api\ClubAchievementController;
use App\Http\Controllers\Api\ClubPlayerController;
use App\Http\Controllers\Api\EventImageController;
use App\Http\Controllers\Api\GalleryAdminController;
use App\Http\Controllers\Api\ImageEditorTemplateController;
use App\Http\Controllers\Api\ImageTemplateController;
use App\Http\Controllers\Api\ImageTemplateSettingController;
use App\Http\Controllers\Api\PersonAmpluaMembershipController;
use App\Http\Controllers\Api\PersonClubMembershipController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\PersonImageController;
use App\Http\Controllers\Api\PersonPositionMembershipController;
use App\Http\Controllers\Api\PersonRoleMembershipController;
use App\Http\Controllers\Api\PersonSportMembershipController;
use App\Http\Controllers\Api\PersonSurnameChangeController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TelegramMessageController;
use App\Http\Controllers\Api\TelegramParseChannelController;
use App\Http\Controllers\Api\TournamentTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventActionController;
use App\Http\Controllers\EventLineupController;
use App\Http\Controllers\EventTeamActionController;
use App\Http\Controllers\ParseTableController;
use App\Http\Controllers\PlayerStatisticsController;
use App\Http\Controllers\PromptTemplateController;
use App\Http\Controllers\TeamActionTypeController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

Route::group(['prefix' => '/v1'], function () {
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
    Route::post('/clubs/{id}/regions', [ApiClubController::class, 'addRegion'])->name('api.clubs.addRegion');
    Route::get('/arenas', [ApiArenaController::class, 'index'])->name('api.arenas.index');
    Route::get('/arenas/{id}', [ApiArenaController::class, 'show'])->name('api.arenas.show');
    Route::get('/competitions', [ApiCompetitionController::class, 'index'])->name('api.competitions.index');
    Route::get('/competitions/{id}', [ApiCompetitionController::class, 'show'])->name('api.competitions.show');
    Route::get('/events', [ApiEventController::class, 'index'])->name('api.events.index');
    Route::get('/events/{id}', [ApiEventController::class, 'show'])->name('api.events.show');
    Route::patch('/events/{event}', [\App\Http\Controllers\Api\ApiEventController::class, 'update']);
    Route::put('/events/{event}', [\App\Http\Controllers\Api\ApiEventController::class, 'update']);
    Route::get('/articles', [ApiArticleController::class, 'index'])->name('api.articles.index');
    Route::get('/articles/{id}', [ApiArticleController::class, 'show'])->name('api.articles.show');
    Route::get('/galleries', [ApiGalleryController::class, 'index'])->name('api.galleries.index');
    Route::get('/galleries/{id}', [ApiGalleryController::class, 'show'])->name('api.galleries.show');
    Route::get('/params', [ApiParamsController::class, 'index'])->name('params.show');
    Route::get('/filters', [ApiParamsController::class, 'getTitle'])->name('params.filters');
    Route::get('/page/{id}', [ApiParamsController::class, 'getPage'])->name('params.page');
    Route::get('/apage/{id}', [ApiParamsController::class, 'getAdminPage'])->name('params.apage');
    Route::get('/amenu', [ApiParamsController::class, 'getAdminMenu'])->name('params.amenu');

        // Маршруты для рейтинга SRRR
    Route::prefix('rating')->group(function () {
        // GET маршруты
        Route::get('/top', [RatingController::class, 'getTopRating']);
        Route::get('/region', [RatingController::class, 'getRegionRating']);
        Route::get('/dynamics', [RatingController::class, 'getRegionDynamics']);
        Route::get('/statistics', [RatingController::class, 'getRegionsStatistics']);
        Route::get('/calculation-details', [RatingController::class, 'getCalculationDetails']);
        Route::get('/regions', [RatingController::class, 'getRegions']);
        Route::get('/sports', [RatingController::class, 'getSports']);
        Route::get('/years', [RatingController::class, 'getYears']);
        Route::post('/calculate-yearly', [RatingController::class, 'calculateYearlyRating'])->middleware('auth:sanctum');
    });

    // CRUD операции для рейтинга (отдельная группа)
    Route::prefix('rating')->middleware('auth:sanctum')->group(function () {
        // CRUD операции для годов рейтинга
        Route::post('/years', [RatingController::class, 'storeYear']);
        Route::put('/years/{id}', [RatingController::class, 'updateYear']);
        Route::delete('/years/{id}', [RatingController::class, 'destroyYear']);

        // CRUD операции для регионов рейтинга
        Route::post('/regions', [RatingController::class, 'storeRegion']);
        Route::put('/regions/{id}', [RatingController::class, 'updateRegion']);
        Route::delete('/regions/{id}', [RatingController::class, 'destroyRegion']);
    });

    // Маршруты для достижений клубов
    Route::prefix('achievements')->group(function () {
        Route::get('/club', [ClubAchievementController::class, 'getClubAchievements']);
        Route::post('/', [ClubAchievementController::class, 'store'])->middleware('auth:sanctum');
        Route::put('/{id}', [ClubAchievementController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [ClubAchievementController::class, 'destroy'])->middleware('auth:sanctum');
        Route::get('/statistics', [ClubAchievementController::class, 'getAchievementsStatistics']);
        // Новый маршрут для массового пересчёта очков
        Route::post('/recalculate-points', [ClubAchievementController::class, 'recalculatePoints'])->middleware('auth:sanctum');
    });

    // Маршруты для типов турниров
    Route::prefix('tournament-types')->group(function () {
        Route::get('/', [TournamentTypeController::class, 'index']);
        Route::get('/{id}', [TournamentTypeController::class, 'show']);
        Route::post('/', [TournamentTypeController::class, 'store'])->middleware('auth:sanctum');
        Route::put('/{id}', [TournamentTypeController::class, 'update'])->middleware('auth:sanctum');
        Route::delete('/{id}', [TournamentTypeController::class, 'destroy'])->middleware('auth:sanctum');
    });

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


    Route::prefix('rating')->group(function () {
        // ...
        Route::get('/region-year-total-ratings', [RatingController::class, 'getRegionYearTotalRatings']);
        Route::get('/region-year-total-ratings-history', [RatingController::class, 'getRegionYearTotalRatingsHistory']);
        Route::get('/actuality-status', [RatingController::class, 'getRatingActualityStatus']);
    });

});

// --- CRUD для шаблонов изображений (без авторизации для админки) ---
Route::prefix('image-templates')->group(function () {
    Route::get('/', [\App\Http\Controllers\ImageTemplateController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\ImageTemplateController::class, 'store']);
    Route::get('/{imageTemplate}', [\App\Http\Controllers\ImageTemplateController::class, 'show']);
    Route::put('/{imageTemplate}', [\App\Http\Controllers\ImageTemplateController::class, 'update']);
    Route::patch('/{imageTemplate}', [\App\Http\Controllers\ImageTemplateController::class, 'update']);
    Route::delete('/{imageTemplate}', [\App\Http\Controllers\ImageTemplateController::class, 'destroy']);
});

// --- CRUD для настроек шаблонов (без авторизации для админки) ---
Route::prefix('image-template-settings')->group(function () {
    Route::get('/', [\App\Http\Controllers\ImageTemplateSettingController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\ImageTemplateSettingController::class, 'store']);
    Route::get('/{imageTemplateSetting}', [\App\Http\Controllers\ImageTemplateSettingController::class, 'show']);
    Route::put('/{imageTemplateSetting}', [\App\Http\Controllers\ImageTemplateSettingController::class, 'update']);
    Route::patch('/{imageTemplateSetting}', [\App\Http\Controllers\ImageTemplateSettingController::class, 'update']);
    Route::delete('/{imageTemplateSetting}', [\App\Http\Controllers\ImageTemplateSettingController::class, 'destroy']);
});

// --- CRUD для изображений событий (без авторизации для админки) ---
Route::prefix('event-images')->group(function () {
    Route::get('/', [\App\Http\Controllers\EventImageController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\EventImageController::class, 'store']);
    Route::get('/{eventImage}', [\App\Http\Controllers\EventImageController::class, 'show']);
    Route::put('/{eventImage}', [\App\Http\Controllers\EventImageController::class, 'update']);
    Route::patch('/{eventImage}', [\App\Http\Controllers\EventImageController::class, 'update']);
    Route::delete('/{eventImage}', [\App\Http\Controllers\EventImageController::class, 'destroy']);
});

// --- CRUD для шаблонов редактора изображений (без авторизации для админки) ---
Route::prefix('image-editor-templates')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ImageEditorTemplateController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\ImageEditorTemplateController::class, 'store']);
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


Route::get('/events-today-map', [ApiEventController::class, 'eventsTodayMap']);



Route::prefix('v1/article_count')->group(function () {
    // Записать просмотр статьи (с защитой от дублирования)
    Route::get('{slug}/record', function ($slug) {
        try {
            $article = \App\Models\Article::where('slug', $slug)->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Статья не найдена'
                ], 404);
            }

            // Проверяем, был ли уже просмотр с этого IP в течение последних 24 часов
            $ipAddress = request()->ip();
            $recentView = \App\Models\ArticleView::where('article_id', $article->id)
                ->where('ip_address', $ipAddress)
                ->where('viewed_at', '>=', now()->subDay())
                ->exists();

            if ($recentView) {
                return response()->json([
                    'success' => true,
                    'views_count' => $article->views ?? 0,
                    'message' => 'Просмотр уже был записан с этого IP в течение 24 часов'
                ]);
            }

            // Используем транзакцию для атомарности операции
            \Illuminate\Support\Facades\DB::transaction(function () use ($article, $ipAddress) {
                // Записываем просмотр
                $currentViews = $article->views ?? 0;
                $article->update(['views' => $currentViews + 1]);

                // Записываем детальную информацию о просмотре
                \App\Models\ArticleView::create([
                    'article_id' => $article->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => request()->userAgent(),
                    'session_id' => null, // Убираем зависимость от сессии для API
                    'viewed_at' => now(),
                ]);
            });

            // Обновляем модель после транзакции
            $article->refresh();

            return response()->json([
                'success' => true,
                'views_count' => $article->views ?? 0,
                'message' => 'Просмотр записан'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }
    });

    // Получить количество просмотров статьи
    Route::get('{slug}/views', function ($slug) {
        try {
            $article = \App\Models\Article::where('slug', $slug)->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Статья не найдена'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'views_count' => $article->views ?? 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }
    });

    // Получить статистику просмотров статьи
    Route::get('{slug}/stats', function ($slug) {
        try {
            $article = \App\Models\Article::where('slug', $slug)->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Статья не найдена'
                ], 404);
            }

            $period = request()->get('period', 'day');
            $query = \App\Models\ArticleView::where('article_id', $article->id);

            switch ($period) {
                case 'hour':
                    $query->where('viewed_at', '>=', now()->subHour());
                    break;
                case 'day':
                    $query->where('viewed_at', '>=', now()->subDay());
                    break;
                case 'week':
                    $query->where('viewed_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('viewed_at', '>=', now()->subMonth());
                    break;
            }

            $totalViews = $query->count();
            $uniqueViews = $query->distinct('ip_address')->count('ip_address');

            return response()->json([
                'success' => true,
                'article_id' => $article->id,
                'article_title' => $article->title,
                'total_views' => $article->views ?? 0,
                'period_stats' => [
                    'total' => $totalViews,
                    'unique' => $uniqueViews,
                    'period' => $period
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage()
            ], 500);
        }
    });
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

// Маршруты для сезонов соревнований
Route::apiResource('competition-seasons', \App\Http\Controllers\Api\CompetitionSeasonController::class);
Route::get('competition-seasons/competition/{competitionId}', [\App\Http\Controllers\Api\CompetitionSeasonController::class, 'byCompetition']);
Route::get('competition-seasons/active', [\App\Http\Controllers\Api\CompetitionSeasonController::class, 'active']);

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
Route::post('/clubs/{club}/add-player-with-amplua', [ClubPlayerController::class, 'addWithAmplua']);
Route::get('/clubs/{club}/players', [ClubPlayerController::class, 'players']);
// Добавление сотрудника с должностью (атомарно)
Route::post('/clubs/{club}/add-staff-with-position', [ClubPlayerController::class, 'addWithPosition']);

Route::post('/arenas/update-contacts', [ApiArenaController::class, 'updateContacts']);

Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent();
})->middleware('web'); // Важно использовать web middleware

Route::post('/upload-image', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'image' => [
            'required',
            'image',
            'mimes:jpeg,png,jpg,gif,webp', // Явное указание разрешенных типов
            'max:20480', // ~20MB
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

// Маршруты для управления персонами
Route::prefix('people')->group(function () {
    Route::get('/', [PersonController::class, 'index']);
    Route::post('/', [PersonController::class, 'store']);

    Route::get('/search', [PersonController::class, 'search']);
    Route::get('/statistics', [PersonController::class, 'statistics']);
    Route::get('/clubs', [PersonController::class, 'clubs']);
    Route::get('/sports', [PersonController::class, 'sports']);
    Route::get('/positions', [PersonController::class, 'positions']);
    Route::get('/ampluas', [PersonController::class, 'ampluas']);
    Route::get('/{person}', [PersonController::class, 'show']);
    Route::put('/{person}', [PersonController::class, 'update']);
    Route::delete('/{person}', [PersonController::class, 'destroy']);

    // Маршруты для изображений персон
    Route::prefix('{person}/images')->group(function () {
        Route::get('/', [PersonController::class, 'getImages']);
        Route::post('/', [PersonController::class, 'uploadImage']);
        Route::post('/delete-multiple', [PersonController::class, 'deleteMultipleImages']);
        Route::post('/update-positions', [PersonController::class, 'updateImagePositions']);
        Route::post('/{imageId}/set-main', [PersonController::class, 'setMainImage']);
        Route::post('/{imageId}/unset-main', [PersonController::class, 'unsetMainImage']);
        Route::delete('/{imageId}', [PersonController::class, 'deleteImage']);
    });

    // Маршруты для членства в клубах
    Route::prefix('{person}/club-memberships')->group(function () {
        Route::get('/', [PersonClubMembershipController::class, 'index']);
        Route::post('/', [PersonClubMembershipController::class, 'store']);
        Route::put('/{membership}', [PersonClubMembershipController::class, 'update']);
        Route::delete('/{membership}', [PersonClubMembershipController::class, 'destroy']);
        Route::post('/{membership}/leave', [PersonClubMembershipController::class, 'leave']);
        Route::get('/active', [PersonClubMembershipController::class, 'active']);
    });

    // Маршруты для членства в видах спорта
    Route::prefix('{person}/sport-memberships')->group(function () {
        Route::get('/', [PersonSportMembershipController::class, 'index']);
        Route::post('/', [PersonSportMembershipController::class, 'store']);
        Route::put('/{membership}', [PersonSportMembershipController::class, 'update']);
        Route::delete('/{membership}', [PersonSportMembershipController::class, 'destroy']);
        Route::post('/{membership}/end', [PersonSportMembershipController::class, 'end']);
        Route::get('/active', [PersonSportMembershipController::class, 'active']);
    });

    // Маршруты для смен фамилий
    Route::prefix('{person}/surname-changes')->group(function () {
        Route::get('/', [PersonSurnameChangeController::class, 'index']);
        Route::post('/', [PersonSurnameChangeController::class, 'store']);
        Route::put('/{surnameChange}', [PersonSurnameChangeController::class, 'update']);
        Route::delete('/{surnameChange}', [PersonSurnameChangeController::class, 'destroy']);
        Route::get('/valid', [PersonSurnameChangeController::class, 'valid']);
        Route::get('/historical', [PersonSurnameChangeController::class, 'historical']);
    });

    // Маршруты для членства в ролях
    Route::prefix('{person}/role-memberships')->group(function () {
        Route::get('/', [PersonRoleMembershipController::class, 'index']);
        Route::post('/', [PersonRoleMembershipController::class, 'store']);
        Route::put('/{membership}', [PersonRoleMembershipController::class, 'update']);
        Route::delete('/{membership}', [PersonRoleMembershipController::class, 'destroy']);
        Route::post('/{membership}/end', [PersonRoleMembershipController::class, 'endMembership']);
        Route::get('/active', [PersonRoleMembershipController::class, 'active']);
        Route::get('/history', [PersonRoleMembershipController::class, 'history']);
    });

    // Маршруты для членства в должностях
    Route::prefix('{person}/position-memberships')->group(function () {
        Route::get('/', [PersonPositionMembershipController::class, 'index']);
        Route::post('/', [PersonPositionMembershipController::class, 'store']);
        Route::put('/{membership}', [PersonPositionMembershipController::class, 'update']);
        Route::delete('/{membership}', [PersonPositionMembershipController::class, 'destroy']);
        Route::post('/{membership}/end', [PersonPositionMembershipController::class, 'endMembership']);
        Route::get('/active', [PersonPositionMembershipController::class, 'active']);
        Route::get('/history', [PersonPositionMembershipController::class, 'history']);
    });

    // Маршруты для членства в амплуа
    Route::prefix('{person}/amplua-memberships')->group(function () {
        Route::get('/', [PersonAmpluaMembershipController::class, 'index']);
        Route::post('/', [PersonAmpluaMembershipController::class, 'store']);
        Route::put('/{membership}', [PersonAmpluaMembershipController::class, 'update']);
        Route::delete('/{membership}', [PersonAmpluaMembershipController::class, 'destroy']);
        Route::post('/{membership}/end', [PersonAmpluaMembershipController::class, 'endMembership']);
        Route::get('/active', [PersonAmpluaMembershipController::class, 'active']);
        Route::get('/history', [PersonAmpluaMembershipController::class, 'history']);
    });
});


Route::post('/people/import', [PersonController::class, 'import']);

// Маршруты для управления ролями
Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::post('/', [RoleController::class, 'store']);
    Route::get('/statistics', [RoleController::class, 'statistics']);
    Route::get('/sportsman', [RoleController::class, 'sportsman']);
    Route::get('/non-sportsman', [RoleController::class, 'nonSportsman']);
    Route::get('/{role}', [RoleController::class, 'show']);
    Route::put('/{role}', [RoleController::class, 'update']);
    Route::delete('/{role}', [RoleController::class, 'destroy']);
});

// Маршруты для статистики членств в ролях
Route::get('/role-memberships/statistics', [PersonRoleMembershipController::class, 'statistics']);

// Маршруты для управления должностями
Route::prefix('positions')->group(function () {
    Route::get('/', [PositionController::class, 'index']);
    Route::post('/', [PositionController::class, 'store']);
    Route::get('/statistics', [PositionController::class, 'statistics']);
    Route::get('/{position}', [PositionController::class, 'show']);
    Route::put('/{position}', [PositionController::class, 'update']);
    Route::delete('/{position}', [PositionController::class, 'destroy']);
});

// Маршруты для управления амплуа
Route::prefix('ampluas')->group(function () {
    Route::get('/', [AmpluaController::class, 'index']);
    Route::post('/', [AmpluaController::class, 'store']);
    Route::get('/statistics', [AmpluaController::class, 'statistics']);
    Route::get('/{amplua}', [AmpluaController::class, 'show']);
    Route::put('/{amplua}', [AmpluaController::class, 'update']);
    Route::delete('/{amplua}', [AmpluaController::class, 'destroy']);
});

Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        abort(404);
    }

    $file = file_get_contents($fullPath);
    $type = mime_content_type($fullPath);

    return response($file, 200, [
        'Content-Type' => $type,
        'Cache-Control' => 'public, max-age=31536000'
    ]);
})->where('path', '.*');

Route::prefix('events/{event}/lineups')->group(function () {
    Route::get('/', [EventLineupController::class, 'index']);
    Route::post('/', [EventLineupController::class, 'store']);
});
Route::apiResource('event-lineups', EventLineupController::class)->except(['index', 'store', 'show']);

Route::prefix('events/{event}/actions')->group(function () {
    Route::get('/', [EventActionController::class, 'index']);
    Route::post('/', [EventActionController::class, 'store']);
});
Route::apiResource('event-actions', EventActionController::class)->except(['index', 'store', 'show']);

Route::apiResource('action-types', ActionTypeController::class)->except(['show']);
Route::apiResource('team-action-types', TeamActionTypeController::class);
Route::apiResource('event-team-actions', EventTeamActionController::class);

// Маршруты для шаблонов изображений
Route::apiResource('image-templates', ImageTemplateController::class);
Route::apiResource('image-template-settings', ImageTemplateSettingController::class);
Route::apiResource('image-editor-templates', ImageEditorTemplateController::class);

// Маршруты для изображений событий
Route::post('/event-images/tmp', [EventImageController::class, 'tmpUpload']);

// Маршруты для статистики игроков
Route::prefix('clubs/{club}/statistics')->group(function () {
    Route::get('/seasons', [PlayerStatisticsController::class, 'getClubSeasons']);
    Route::get('/players', [PlayerStatisticsController::class, 'getPlayerStatsOverall']);
    Route::get('/players/{season}', [PlayerStatisticsController::class, 'getPlayerStatsBySeason']);
    Route::get('/players/competition/{competition}', [PlayerStatisticsController::class, 'getPlayerStatsByCompetition']);
});




