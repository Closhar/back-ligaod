<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $searchQuery = $request->input('q');

        $query = Gallery::query()
        ->with('main_image')
        ->with('images');

        if ($searchQuery) {
            $query->where('title', 'LIKE', "%{$searchQuery}%");
        }

        $galleries = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'current_page' => $galleries->currentPage(),
            'data' => $galleries->items(),
            'first_page_url' => $galleries->url(1),
            'from' => $galleries->firstItem(),
            'last_page' => $galleries->lastPage(),
            'last_page_url' => $galleries->url($galleries->lastPage()),
            'links' => $galleries->links(),
            'next_page_url' => $galleries->nextPageUrl(),
            'path' => $galleries->path(),
            'per_page' => $galleries->perPage(),
            'prev_page_url' => $galleries->previousPageUrl(),
            'to' => $galleries->lastItem(),
            'total' => $galleries->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'image_id' => 'required|integer|exists:images,id',
            ]);

            $gallery = Gallery::create($validated);

            return response()->json($gallery, 201);

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
            $gallery = Gallery::findOrFail($id);
            return response()->json($gallery);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $gallery = Gallery::findOrFail($id);

            $validated = $request->validate([
                'title' => 'string|max:255',
                'image_id' => 'integer|exists:images,id',
            ]);

            $gallery->update($validated);

            return response()->json([
                'success' => true,
                'data' => $gallery,
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
            $gallery = Gallery::findOrFail($id);
            $gallery->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
