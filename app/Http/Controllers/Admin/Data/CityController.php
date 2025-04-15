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

            $query = City::query();

            if ($searchQuery) {
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }

            if ($request->has('type') && $request->input('type') === 'async') {
                return $query->orderBy('title')->limit($limit)->get()->toArray();
            }

            $cities = $query->orderBy('title')->paginate($perPage);

            return [
                'data' => $cities->items(),
                'pagination' => [
                    'total' => $cities->total(),
                    'per_page' => $cities->perPage(),
                    'current_page' => $cities->currentPage(),
                    'last_page' => $cities->lastPage()
                ]
            ];

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
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
                'title' => 'required|string|max:255',
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
