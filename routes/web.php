<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ParserController;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
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
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

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

// Parser routes
Route::prefix('admin/parser')->name('admin.parser.')->middleware(['auth'])->group(function () {
    Route::get('/', [ParserController::class, 'index'])->name('index');
    Route::get('/create', [ParserController::class, 'create'])->name('create');
    Route::post('/', [ParserController::class, 'store'])->name('store');
    Route::get('/{template}', [ParserController::class, 'show'])->name('show');
    Route::get('/{template}/edit', [ParserController::class, 'edit'])->name('edit');
    Route::put('/{template}', [ParserController::class, 'update'])->name('update');
    Route::delete('/{template}', [ParserController::class, 'destroy'])->name('destroy');
    Route::post('/{template}/test', [ParserController::class, 'test'])->name('test');
    Route::post('/{template}/parse', [ParserController::class, 'parse'])->name('parse');
    Route::patch('/{template}/toggle', [ParserController::class, 'toggleActive'])->name('toggle');
});
