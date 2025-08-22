<?php

namespace App\Http\Controllers;

use App\Models\TeamActionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamActionTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $teamActionTypes = TeamActionType::orderBy('name')->get();

        return response()->json($teamActionTypes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:100'
        ]);

        $teamActionType = TeamActionType::create($validated);
        return response()->json($teamActionType, 201);
    }

    public function update(Request $request, TeamActionType $teamActionType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:100'
        ]);

        $teamActionType->update($validated);
        return response()->json($teamActionType);
    }

    public function destroy(TeamActionType $teamActionType)
    {
        $teamActionType->delete();
        return response()->json(null, 204);
    }
}
