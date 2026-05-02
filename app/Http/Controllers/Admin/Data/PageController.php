<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 30);
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSortFields = ['id', 'title', 'slug', 'menu_sort', 'mobile_menu_sort'];

        $query = Page::query();

        if ($request->filled('id')) {
            $query->where('id', $request->input('id'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%")
                    ->orWhere('html', 'like', "%{$search}%");
            });
        }

        if ($request->filled('in_menu')) {
            $query->where('in_menu', (bool) $request->boolean('in_menu'));
        }

        if ($request->filled('in_mobile_menu')) {
            $query->where('in_mobile_menu', (bool) $request->boolean('in_mobile_menu'));
        }

        if (!in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'id';
        }

        $pages = $query
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage);

        return response()->json($pages);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $page = Page::create($this->validatedData($request, null, true));

            return response()->json($page, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show(Page $page): JsonResponse
    {
        return response()->json($page);
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        try {
            $page->update($this->validatedData($request, $page->id));

            return response()->json([
                'success' => true,
                'data' => $page->fresh(),
                'message' => 'Updated successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy(Page $page): JsonResponse
    {
        foreach (['image', 'image_default'] as $field) {
            if ($page->{$field}) {
                Storage::disk('public')->delete($page->{$field});
            }
        }

        $page->delete();

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $field = $request->input('field', 'image');

        $validator = Validator::make($request->all(), [
            'field' => ['nullable', Rule::in(['image', 'image_default'])],
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($page->{$field}) {
            Storage::disk('public')->delete($page->{$field});
        }

        $path = $request->file('image')->store('pages', 'public');
        $page->{$field} = $path;
        $page->save();

        return response()->json([
            'success' => true,
            'image_path' => $path,
            'full_path' => config('app.url') . '/storage/' . $path,
            'message' => 'Изображение страницы успешно загружено',
        ]);
    }

    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $field = $request->input('field', 'image');

        if (!in_array($field, ['image', 'image_default'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Недопустимое поле изображения',
            ], 422);
        }

        if ($page->{$field}) {
            Storage::disk('public')->delete($page->{$field});
        }

        $page->{$field} = null;
        $page->save();

        return response()->json([
            'success' => true,
            'message' => 'Изображение страницы удалено',
        ]);
    }

    private function validatedData(Request $request, ?int $pageId = null, bool $creating = false): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => [$creating ? 'required' : 'sometimes', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($pageId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:255'],
            'image_default' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
            'in_menu' => ['nullable', 'boolean'],
            'menu_sort' => ['nullable', 'integer', 'min:0'],
            'in_mobile_menu' => ['nullable', 'boolean'],
            'mobile_menu_sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
