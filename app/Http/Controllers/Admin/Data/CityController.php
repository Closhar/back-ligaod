<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $searchQuery = $request->input('q');
            $limit = $request->input('limit', $perPage);
            $cityId = $request->input('id');
            $field = $request->input('field');

            $query = City::query();

            if ($cityId) {
                $query->where('id', $cityId);
            }

            if ($field) {
                return $this->getFieldStatistics($query, $field, $searchQuery);
            }

            if ($searchQuery) {
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }

            if ($request->has('type') && $request->input('type') === 'async') {
                return $query->orderBy('title')->limit($limit)->get()->toArray();
            }

            $cities = $query->orderBy('title')->paginate($perPage);

            return [
                'current_page' => $cities->currentPage(),
                'data' => $cities->items(),
                'first_page_url' => $cities->url(1),
                'from' => $cities->firstItem(),
                'last_page' => $cities->lastPage(),
                'last_page_url' => $cities->url($cities->lastPage()),
                'links' => $cities->links(),
                'next_page_url' => $cities->nextPageUrl(),
                'path' => $cities->path(),
                'per_page' => $cities->perPage(),
                'prev_page_url' => $cities->previousPageUrl(),
                'to' => $cities->lastItem(),
                'total' => $cities->total(),
            ];

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for a specific field.
     */
    private function getFieldStatistics($query, $field, $searchQuery = null)
    {
        if (!in_array($field, ['title', 'title_short'])) {
            return response()->json([
                'error' => 'Invalid field',
                'message' => "Field '{$field}' is not allowed for statistics"
            ], 400);
        }

        if ($searchQuery) {
            if ($field === 'title') {
                $query->where($field, 'LIKE', "%{$searchQuery}%");
            } else {
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }
        }

        $result = $query->select($field, 'id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy($field, 'id')
            ->get()
            ->map(function($item) use ($field) {
                return [
                    'id' => $item->id,
                    $field => $item->{$field},
                    'count' => $item->count
                ];
            });

        return $result;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255|unique:cities,title',
                'title_short' => 'required|string|max:50',
            ]);

            $city = City::create($validated);

            return response()->json([
                'success' => true,
                'data' => $city,
                'message' => 'City created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $city = City::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $city
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'City not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255|unique:cities,title,' . $id,
                'title_short' => 'required|string|max:50',
            ]);

            $city = City::findOrFail($id);
            $city->update($validated);

            return response()->json([
                'success' => true,
                'data' => $city,
                'message' => 'City updated successfully'
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
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $city = City::findOrFail($id);
            $city->delete();

            return response()->json([
                'success' => true,
                'message' => 'City deleted successfully'
            ], 204);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }
}
