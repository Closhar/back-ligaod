<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PicParam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PicParamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PicParam::query()->select(['id', 'title', 'name', 'value']);
        $sortField = $request->query('sort_field', 'title');
        $sortDirection = $request->query('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        if (!in_array($sortField, ['id', 'title', 'name'], true)) {
            $sortField = 'title';
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('value', 'LIKE', "%{$search}%");
            });
        }

        $params = $query
            ->orderBy($sortField, $sortDirection)
            ->paginate((int) $request->query('per_page', 50));

        $params->getCollection()->transform(function (PicParam $param) {
            $param->setAttribute('full_path', $this->publicUrl($param->value));

            return $param;
        });

        return response()->json($params);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255', 'unique:pic_params,name'],
                'value' => ['nullable', 'string', 'max:2000'],
            ]);
            $data['value'] = $data['value'] ?? '';

            $param = PicParam::create($data);
            $param->setAttribute('full_path', $this->publicUrl($param->value));

            return response()->json($param, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show(PicParam $picParam): JsonResponse
    {
        $picParam->setAttribute('full_path', $this->publicUrl($picParam->value));

        return response()->json($picParam);
    }

    public function update(Request $request, PicParam $picParam): JsonResponse
    {
        try {
            $data = $request->validate([
                'title' => ['sometimes', 'required', 'string', 'max:255'],
                'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('pic_params', 'name')->ignore($picParam->id)],
                'value' => ['nullable', 'string', 'max:2000'],
            ]);
            if (array_key_exists('value', $data)) {
                $data['value'] = $data['value'] ?? '';
            }

            $picParam->update($data);
            $picParam = $picParam->fresh();
            $picParam->setAttribute('full_path', $this->publicUrl($picParam->value));

            return response()->json([
                'success' => true,
                'data' => $picParam,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy(PicParam $picParam): JsonResponse
    {
        $this->deleteStoredFile($picParam->value);
        $picParam->delete();

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $picParam = PicParam::findOrFail($id);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
        ]);

        $this->deleteStoredFile($picParam->value);

        $path = $request->file('image')->store('params', 'public');
        $picParam->value = $path;
        $picParam->save();

        return response()->json([
            'success' => true,
            'image_path' => $path,
            'full_path' => Storage::disk('public')->url($path),
            'message' => 'Изображение параметра успешно загружено',
        ]);
    }

    public function deleteImage(int $id): JsonResponse
    {
        $picParam = PicParam::findOrFail($id);

        $this->deleteStoredFile($picParam->value);
        $picParam->value = '';
        $picParam->save();

        return response()->json([
            'success' => true,
            'message' => 'Изображение параметра удалено',
        ]);
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : Storage::disk('public')->url($path);
    }

    private function deleteStoredFile(?string $path): void
    {
        if (!$path || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
