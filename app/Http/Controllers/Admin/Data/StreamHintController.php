<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\StreamHint;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StreamHintController extends Controller
{
    public function index(Request $request)
    {
        $query = StreamHint::query();

        // Фильтрация по ID
        if ($request->has('id')) {
            $query->where('id', $request->input('id'));
        }

        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'hint' => 'required|string|max:255'
            ]);

            $streamHint = StreamHint::create($validated);

            return response()->json($streamHint, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $streamHint = StreamHint::findOrFail($id);
            return response()->json($streamHint);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, StreamHint $streamHint)
    {
        try {
            $validated = $request->validate([
                'hint' => 'required|string|max:255'
            ]);

            $streamHint->update($validated);

            return response()->json($streamHint);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(StreamHint $streamHint)
    {
        try {
            $streamHint->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
