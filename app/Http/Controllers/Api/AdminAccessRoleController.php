<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminAccessRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->query('type') === 'async') {
            return response()->json(
                AdminRole::active()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (AdminRole $role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'title' => $role->name,
                    ])
                    ->values()
            );
        }

        $query = AdminRole::query()
            ->with(['adminPages:id,title,slug'])
            ->withCount(['users', 'adminPages']);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->query('is_active') !== null) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(
            $query->orderBy($request->query('sort_field', 'name'), $request->query('sort_direction', 'asc'))
                ->paginate((int) $request->query('per_page', 30))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $role = AdminRole::create($data);
        $role->adminPages()->sync($role->slug === 'user' ? [] : $request->input('admin_page_ids', []));

        return response()->json($this->loadRole($role), 201);
    }

    public function show(AdminRole $adminAccessRole): JsonResponse
    {
        return response()->json($this->loadRole($adminAccessRole));
    }

    public function update(Request $request, AdminRole $adminAccessRole): JsonResponse
    {
        $data = $this->validatedData($request, $adminAccessRole);
        $adminAccessRole->update($data);

        if ($request->has('admin_page_ids')) {
            $adminAccessRole->adminPages()->sync($adminAccessRole->slug === 'user' ? [] : $request->input('admin_page_ids', []));
        }

        return response()->json($this->loadRole($adminAccessRole));
    }

    public function destroy(AdminRole $adminAccessRole): JsonResponse
    {
        if ($adminAccessRole->slug === 'user') {
            return response()->json(['message' => 'Базовую роль Пользователь нельзя удалить'], 422);
        }

        $adminAccessRole->delete();

        return response()->json(null, 204);
    }

    private function validatedData(Request $request, ?AdminRole $role = null): array
    {
        $data = $request->validate([
            'name' => [$role ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('admin_roles', 'slug')->ignore($role?->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'admin_page_ids' => ['sometimes', 'array'],
            'admin_page_ids.*' => ['integer', 'exists:admin_pages,id'],
        ]);

        if (! isset($data['slug']) || $data['slug'] === '') {
            $data['slug'] = Str::slug($data['name'] ?? $role?->name ?? '');
        }

        unset($data['admin_page_ids']);

        return $data;
    }

    private function loadRole(AdminRole $role): AdminRole
    {
        return $role->fresh()
            ->load(['adminPages:id,title,slug'])
            ->loadCount(['users', 'adminPages']);
    }
}
