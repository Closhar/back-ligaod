<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function show(Request $request)
    {
        // Проверяем, существует ли пользователь
        if (!$request->user()) {
            // Если пользователь не найден, выполняем выход из системы
            Auth::logout();

            // Возвращаем сообщение или статус, указывающий, что пользователь не найден
            return response()->json(['message' => 'Пользователь не найден'], 404);
        } // Если пользователь найден, возвращаем его данные
        else return $request->user();
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        // Проверка аутентификации
        if (!$user) {
            return response()->json(['message' => 'Пользователь не аутентифицирован'], 401)
                ->header('Access-Control-Allow-Origin', '*');
        }

        // Валидация данных
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Обновление имени
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        // Обновление аватара
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return response()->json($user)
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        // Получаем текущего аутентифицированного пользователя
        $user = $request->user();

        // Проверяем, есть ли у пользователя аватар
        if ($user->avatar) {
            // Удаляем файл аватара из хранилища
            Storage::disk('public')->delete($user->avatar);

            // Обновляем запись пользователя, удаляя ссылку на аватар
            $user->avatar = null;
            $user->save();
        }

        // Возвращаем успешный ответ
        return response()->json([
            'message' => 'Аватар успешно удален.',
            'user' => $user,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Проверка аутентификации
        if (!$user) {
            return response()->json(['message' => 'Пользователь не аутентифицирован'], 401)
                ->header('Access-Control-Allow-Origin', '*');
        }

        // Валидация данных
        $request->validate([
            'old_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Проверка старого пароля
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Старый пароль неверный'], 422)
                ->header('Access-Control-Allow-Origin', '*');
        }

        // Установка нового пароля
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Пароль успешно изменен'])
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function updateAvatar(Request $request)
    {
        $user = $request->user();
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = Storage::disk('public')->url($path);
            $user->save();
        }
        return response()->json($user);
    }
}
