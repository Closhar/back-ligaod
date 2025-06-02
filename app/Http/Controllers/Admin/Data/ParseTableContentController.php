<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\ParseTableContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ParseTableContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->query('q');
        $perPage = $request->query('per_page', 15);
        $searchId = $request->query('id');
        $tableId = $request->query('table_id');
        $fieldParam = $request->query('field');
        $type = $request->query('type');

        $query = ParseTableContent::query()
            ->select(
                'id',
                'table_id',
                'field1', 'field2', 'field3', 'field4', 'field5',
                'field6', 'field7', 'field8', 'field9', 'field10',
                'field11', 'field12', 'field13', 'field14', 'field15',
                'field16', 'field17', 'field18', 'field19', 'field20'
            );

        if ($searchId) {
            $query->where('id', $searchId);
        }

        if ($tableId) {
            $query->where('table_id', $tableId);
        }

        if ($searchQuery) {
            if ($fieldParam) {
                $query->where($fieldParam, 'LIKE', "%{$searchQuery}%");
            } else {
                $query->where('field1', 'LIKE', "%{$searchQuery}%");
            }
        }

        if ($type === 'async') {
            return $query->get();
        }

        $contents = $query->paginate($perPage);
        $total = $contents->total();

        return [
            'current_page' => $contents->currentPage(),
            'data' => $contents->items(),
            'first_page_url' => $contents->url(1),
            'from' => $contents->firstItem(),
            'last_page' => $contents->lastPage(),
            'last_page_url' => $contents->url($contents->lastPage()),
            'links' => $contents->links(),
            'next_page_url' => $contents->nextPageUrl(),
            'path' => $contents->path(),
            'per_page' => $contents->perPage(),
            'prev_page_url' => $contents->previousPageUrl(),
            'to' => $contents->lastItem(),
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
                'table_id' => 'required|exists:parse_tables,id',
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

            $item = ParseTableContent::create($validated);

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
            $item = ParseTableContent::with('table')->findOrFail($id);
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
                'table_id' => 'exists:parse_tables,id',
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

            $item = ParseTableContent::findOrFail($id);
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
            $item = ParseTableContent::findOrFail($id);
            $item->delete();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
