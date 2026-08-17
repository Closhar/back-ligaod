<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['adminRoles:id,name,slug,is_active'])
            ->withCount('adminRoles');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        foreach (['is_admin', 'is_blocked'] as $field) {
            if ($request->has($field) && $request->query($field) !== null) {
                $query->where($field, filter_var($request->query($field), FILTER_VALIDATE_BOOLEAN));
            }
        }

        $users = $query
            ->orderBy($request->query('sort_field', 'created_at'), $request->query('sort_direction', 'desc'))
            ->paginate((int) $request->query('per_page', 30));

        $users->getCollection()->transform(fn (User $user) => $this->serializeUser($user));

        return response()->json($users);
    }

    public function show(User $adminUser): JsonResponse
    {
        return response()->json($this->serializeUser($adminUser->load('adminRoles:id,name,slug,is_active')));
    }

    public function update(Request $request, User $adminUser): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($adminUser->id)],
            'email_verified' => ['sometimes', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
            'is_blocked' => ['sometimes', 'boolean'],
            'admin_role_ids' => ['sometimes', 'array'],
            'admin_role_ids.*' => ['integer', 'exists:admin_roles,id'],
        ]);

        $emailVerified = $data['email_verified'] ?? null;
        if (array_key_exists('email_verified', $data)) {
            unset($data['email_verified']);
        }

        if (array_key_exists('is_blocked', $data)) {
            $data['blocked_at'] = $data['is_blocked'] ? ($adminUser->blocked_at ?: now()) : null;
        }

        $roleIds = $data['admin_role_ids'] ?? null;
        unset($data['admin_role_ids']);

        $adminUser->update($data);

        if ($emailVerified !== null) {
            $adminUser->forceFill([
                'email_verified_at' => $emailVerified ? now() : null,
            ])->save();
        }

        if (is_array($roleIds)) {
            $adminUser->adminRoles()->sync($roleIds);
        }

        return response()->json($this->serializeUser($adminUser->fresh()->load('adminRoles:id,name,slug,is_active')));
    }

    public function destroy(User $adminUser): JsonResponse
    {
        $adminUser->delete();

        return response()->json(null, 204);
    }

    public function updatePassword(Request $request, User $adminUser): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $adminUser->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return response()->json(['message' => 'Пароль изменен']);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_path' => $user->avatar_path,
            'is_admin' => (bool) $user->is_admin,
            'is_blocked' => (bool) $user->is_blocked,
            'blocked_at' => optional($user->blocked_at)->toDateTimeString(),
            'email_verified_at' => optional($user->email_verified_at)->toDateTimeString(),
            'email_verified' => $user->email_verified_at !== null,
            'registration_type' => $this->registrationType($user),
            'created_at' => optional($user->created_at)->toDateTimeString(),
            'admin_roles' => $user->adminRoles->values(),
            'admin_role_ids' => $user->adminRoles->pluck('id')->values(),
            'admin_roles_count' => $user->admin_roles_count ?? $user->adminRoles->count(),
        ];
    }

    private function registrationType(User $user): string
    {
        if ($user->yandex_id) {
            return 'yandex';
        }

        if ($user->google_id) {
            return 'google';
        }

        return 'email';
    }
}
