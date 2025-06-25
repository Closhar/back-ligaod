<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $searchQuery = $request->input('q');
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');
        $id = $request->input('id');

        $query = Video::query();

        if ($id) {
            $query->where('id', $id);
        }

        if ($searchQuery) {
            $query->where('title', 'LIKE', "%{$searchQuery}%");
        }

        $query->orderBy($sortField, $sortDirection);
        $videos = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'current_page' => $videos->currentPage(),
            'data' => $videos->items(),
            'first_page_url' => $videos->url(1),
            'from' => $videos->firstItem(),
            'last_page' => $videos->lastPage(),
            'last_page_url' => $videos->url($videos->lastPage()),
            'links' => $videos->links(),
            'next_page_url' => $videos->nextPageUrl(),
            'path' => $videos->path(),
            'per_page' => $videos->perPage(),
            'prev_page_url' => $videos->previousPageUrl(),
            'to' => $videos->lastItem(),
            'total' => $videos->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'src' => 'required|string|max:1000',
            ]);

            $video = Video::create($validated);

            return response()->json($video, 201);

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
            $video = Video::findOrFail($id);
            return response()->json($video);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $video = Video::findOrFail($id);

            $validated = $request->validate([
                'title' => 'string|max:255',
                'src' => 'string|max:1000',
            ]);

            $video->update($validated);

            return response()->json([
                'success' => true,
                'data' => $video,
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
            $video = Video::findOrFail($id);
            $video->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
