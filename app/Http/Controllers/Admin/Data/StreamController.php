<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StreamController extends Controller
{
    public function index(Request $request)
    {
        // Retrieve streams with filtering and pagination
        return Stream::paginate($request->input('per_page', 10));
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date_format:Y-m-d H:i:s',
                'title' => 'required|string|max:255',
                'link' => 'required|url',
            ]);

            $stream = Stream::create($validated);

            return response()->json($stream, 201);
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
            $stream = Stream::findOrFail($id);
            return response()->json($stream);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $stream = Stream::findOrFail($id);
            $validated = $request->validate([
                'date' => 'date_format:Y-m-d H:i:s',
                'title' => 'string|max:255',
                'link' => 'url',
            ]);

            $stream->update($validated);

            return response()->json([
                'success' => true,
                'data' => $stream,
                'message' => 'Updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $stream = Stream::findOrFail($id);
            $stream->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}