<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\ParseTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ParseTableController extends Controller
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
        $type = $request->query('type');

        $query = ParseTable::query()
            ->select(
                'id',
                'title',
                'description',
                'field1', 'field2', 'field3', 'field4', 'field5',
                'field6', 'field7', 'field8', 'field9', 'field10',
                'field11', 'field12', 'field13', 'field14', 'field15',
                'field16', 'field17', 'field18', 'field19', 'field20'
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

        if ($type === 'async') {
            return $query->get();
        }

        $tables = $query->paginate($perPage);
        $total = $tables->total();

        return [
            'current_page' => $tables->currentPage(),
            'data' => $tables->items(),
            'first_page_url' => $tables->url(1),
            'from' => $tables->firstItem(),
            'last_page' => $tables->lastPage(),
            'last_page_url' => $tables->url($tables->lastPage()),
            'links' => $tables->links(),
            'next_page_url' => $tables->nextPageUrl(),
            'path' => $tables->path(),
            'per_page' => $tables->perPage(),
            'prev_page_url' => $tables->previousPageUrl(),
            'to' => $tables->lastItem(),
            'total' => $total,
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'field1' => 'nullable|string|max:255',
                'field2' => 'nullable|string|max:255',
                'field3' => 'nullable|string|max:255',
                'field4' => 'nullable|string|max:255',
                'field5' => 'nullable|string|max:255',
                'field6' => 'nullable|string|max:255',
                'field7' => 'nullable|string|max:255',
                'field8' => 'nullable|string|max:255',
                'field9' => 'nullable|string|max:255',
                'field10' => 'nullable|string|max:255',
                'field11' => 'nullable|string|max:255',
                'field12' => 'nullable|string|max:255',
                'field13' => 'nullable|string|max:255',
                'field14' => 'nullable|string|max:255',
                'field15' => 'nullable|string|max:255',
                'field16' => 'nullable|string|max:255',
                'field17' => 'nullable|string|max:255',
                'field18' => 'nullable|string|max:255',
                'field19' => 'nullable|string|max:255',
                'field20' => 'nullable|string|max:255',
            ]);

            $item = ParseTable::create($validated);

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

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = ParseTable::with('contents')->findOrFail($id);
            return response()->json($item);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'string|max:255',
                'description' => 'nullable|string',
                'field1' => 'nullable|string|max:255',
                'field2' => 'nullable|string|max:255',
                'field3' => 'nullable|string|max:255',
                'field4' => 'nullable|string|max:255',
                'field5' => 'nullable|string|max:255',
                'field6' => 'nullable|string|max:255',
                'field7' => 'nullable|string|max:255',
                'field8' => 'nullable|string|max:255',
                'field9' => 'nullable|string|max:255',
                'field10' => 'nullable|string|max:255',
                'field11' => 'nullable|string|max:255',
                'field12' => 'nullable|string|max:255',
                'field13' => 'nullable|string|max:255',
                'field14' => 'nullable|string|max:255',
                'field15' => 'nullable|string|max:255',
                'field16' => 'nullable|string|max:255',
                'field17' => 'nullable|string|max:255',
                'field18' => 'nullable|string|max:255',
                'field19' => 'nullable|string|max:255',
                'field20' => 'nullable|string|max:255',
            ]);

            $item = ParseTable::findOrFail($id);
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $item = ParseTable::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
