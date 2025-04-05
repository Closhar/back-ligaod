<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Http\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): \Illuminate\Http\JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

// Проверяем, существует ли пользователь с таким email
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
// Создаем нового пользователя
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(uniqid()), // Генерируем случайный пароль
                ]);
            }

// Авторизуем пользователя
            Auth::login($user);

// Создаем токен Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Ошибка при авторизации через Google: ' . $e->getMessage()], 500);
        }
    }
}
