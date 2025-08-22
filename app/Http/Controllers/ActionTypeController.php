<?php

namespace App\Http\Controllers;

use App\Models\ActionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $actionTypes = ActionType::orderBy('name')->get();

        return response()->json($actionTypes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'group' => 'required|integer|between:1,4',
            'points' => 'nullable|numeric'
        ]);

        $actionType = ActionType::create($validated);
        return response()->json($actionType, 201);
    }

    public function update(Request $request, ActionType $actionType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'group' => 'required|integer|between:1,4',
            'points' => 'nullable|numeric'
        ]);

        $actionType->update($validated);
        return response()->json($actionType);
    }

    public function destroy(ActionType $actionType)
    {
        $actionType->delete();
        return response()->json(null, 204);
    }
}
