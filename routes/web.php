<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

//Route::get('/', function () {
//    return Inertia::render('Welcome', [
//        'canLogin' => Route::has('login'),
//        'canRegister' => Route::has('register'),
//        'laravelVersion' => Application::VERSION,
//        'phpVersion' => PHP_VERSION,
//    ]);
//});
//
//Route::get('/dashboard', function () {
//    return Inertia::render('Dashboard');
//})->middleware(['auth', 'verified'])->name('dashboard');
//
//Route::middleware('auth')->group(function () {
//    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
//});

//require __DIR__ . '/auth.php';
//
//Auth::routes();
//
//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
//    Route::get('login', [AuthenticatedSessionController::class, 'create'])
//        ->name('login');
//
//    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('/auth/google/redirect', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/google/callback', function () {
    $googleUser = Socialite::driver('google')->user();

    // Создаем или находим пользователя
    $user = User::firstOrCreate(
        ['email' => $googleUser->getEmail()],
        [
            'name' => $googleUser->getName(),
            'avatar' => $googleUser->getAvatar(),
            'password' => bcrypt(Str::random(24)),
        ]
    );

    // Создаем токен
    $token = $user->createToken('google-token')->plainTextToken;

    // Перенаправляем на фронтенд с токеном в URL
    //return redirect('http://localhost:3000/auth/google/callback?token=' . $token);
    return redirect(env('NUXT_URL') . '/auth/google/callback?token=' . $token);
});

Route::get('/auth/yandex/redirect', function () {
    $query = http_build_query([
        'response_type' => 'code',
        'client_id' => config('services.yandex.client_id'),
        'redirect_uri' => config('services.yandex.redirect'),
        'force_confirm' => 'yes',
    ]);

    return redirect('https://oauth.yandex.ru/authorize?' . $query);
});

Route::get('/auth/yandex/callback', function () {
    $code = request('code');
    $frontendUrl = rtrim((string) env('NUXT_URL'), '/');

    if (!$code) {
        return redirect($frontendUrl . '/account');
    }

    $tokenResponse = Http::asForm()->post('https://oauth.yandex.ru/token', [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => config('services.yandex.client_id'),
        'client_secret' => config('services.yandex.client_secret'),
        'redirect_uri' => config('services.yandex.redirect'),
    ]);

    if (!$tokenResponse->ok() || !$tokenResponse->json('access_token')) {
        return redirect($frontendUrl . '/account');
    }

    $profileResponse = Http::withToken($tokenResponse->json('access_token'))
        ->get('https://login.yandex.ru/info', [
            'format' => 'json',
        ]);

    if (!$profileResponse->ok() || !$profileResponse->json('id')) {
        return redirect($frontendUrl . '/account');
    }

    $yandexId = (string) $profileResponse->json('id');
    $email = $profileResponse->json('default_email') ?: "yandex_{$yandexId}@oauth.local";
    $name = $profileResponse->json('real_name')
        ?: $profileResponse->json('display_name')
        ?: $profileResponse->json('login')
        ?: 'Пользователь Яндекса';
    $avatarId = $profileResponse->json('default_avatar_id');
    $avatar = $avatarId ? "https://avatars.yandex.net/get-yapic/{$avatarId}/islands-200" : null;

    $user = User::where('yandex_id', $yandexId)->first()
        ?: User::where('email', $email)->first()
        ?: new User();

    $user->fill([
        'name' => $user->name ?: $name,
        'email' => $user->email ?: $email,
        'avatar' => $user->avatar ?: $avatar,
        'yandex_id' => $yandexId,
        'password' => $user->password ?: bcrypt(Str::random(32)),
    ]);

    if (!$user->email_verified_at) {
        $user->email_verified_at = now();
    }

    $user->save();

    $token = $user->createToken('yandex-token')->plainTextToken;

    return redirect($frontendUrl . '/auth/yandex/callback?token=' . $token);
});
