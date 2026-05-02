<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Param;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Param::query()->select(['id', 'title', 'name', 'value', 'type']);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('value', 'LIKE', "%{$search}%");
            });
        }

        $params = $query
            ->orderBy('title')
            ->paginate((int) $request->query('per_page', 50));

        return response()->json($params);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255', 'unique:params,name'],
                'value' => ['nullable', 'string'],
                'type' => ['required', Rule::in(['string', 'text'])],
            ]);

            $param = Param::create($data);

            return response()->json($param, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show(Param $param): JsonResponse
    {
        return response()->json($param);
    }

    public function update(Request $request, Param $param): JsonResponse
    {
        try {
            $data = $request->validate([
                'title' => ['sometimes', 'required', 'string', 'max:255'],
                'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('params', 'name')->ignore($param->id)],
                'value' => ['nullable', 'string'],
                'type' => ['sometimes', 'required', Rule::in(['string', 'text'])],
            ]);

            $param->update($data);

            return response()->json([
                'success' => true,
                'data' => $param->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy(Param $param): JsonResponse
    {
        $param->delete();

        return response()->json(null, 204);
    }
}
