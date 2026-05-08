<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Пользователь заблокирован.',
            ]);
        }

        if (!$user->hasAnyAdminAccess()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => __('auth.admin_only'),
            ]);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                ...$user->only('id', 'name', 'email', 'is_admin'),
                'is_blocked' => (bool) $user->is_blocked,
                'admin_roles' => $user->activeAdminRoles()->get(['admin_roles.id', 'admin_roles.name', 'admin_roles.slug']),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Успешный выход']);
    }
}
