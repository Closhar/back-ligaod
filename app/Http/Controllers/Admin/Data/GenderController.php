<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Gender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GenderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->query('q');
        $perPage = $request->query('per_page', 15);
        $searchId = $request->query('id');
        $fieldParam = $request->query('field');

        $query = Gender::query()
            ->select(
                'id',
                'title',
                'title_short',
                'icon',
                'slug'
            );

        if ($searchId) {
            $query->where('id', $searchId);
        }

        if ($searchQuery) {
            if ($fieldParam) {
                $query->where($fieldParam, 'LIKE', "%{$searchQuery}%");
            } else {
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }
        }

        $genders = $query->paginate($perPage);
        $total = $genders->total();

        return [
            'current_page' => $genders->currentPage(),
            'data' => $genders->items(),
            'first_page_url' => $genders->url(1),
            'from' => $genders->firstItem(),
            'last_page' => $genders->lastPage(),
            'last_page_url' => $genders->url($genders->lastPage()),
            'links' => $genders->links(),
            'next_page_url' => $genders->nextPageUrl(),
            'path' => $genders->path(),
            'per_page' => $genders->perPage(),
            'prev_page_url' => $genders->previousPageUrl(),
            'to' => $genders->lastItem(),
            'total' => $total,
        ];
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255|unique:genders',
                'title_short' => 'required|string|max:255',
                'icon' => 'string|max:255',
                'slug' => 'string|max:255|unique:genders',
            ]);

            $item = Gender::create($validated);

            return response()->json($item, 201);

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
            $item = Gender::findOrFail($id);
            return response()->json($item);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'string|max:255|unique:genders,title,' . $id,
                'title_short' => 'string|max:255',
                'icon' => 'string|max:255',
                'slug' => 'string|max:255|nullable|unique:genders,slug,' . $id,
            ]);

            $item = Gender::findOrFail($id);
            $item->update($validated);

            return response()->json([
                'success' => true,
                'data' => $item,
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
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $item = Gender::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
