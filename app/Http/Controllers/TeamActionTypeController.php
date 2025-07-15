<?php
namespace App\Http\Controllers;

use App\Models\TeamActionType;
use Illuminate\Http\Request;

class TeamActionTypeController extends Controller
{
    public function index()
    {
        return TeamActionType::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'icon' => 'nullable|string',
            'short_name' => 'nullable|string',
        ]);
        return TeamActionType::create($data);
    }

    public function update(Request $request, TeamActionType $teamActionType)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'icon' => 'nullable|string',
            'short_name' => 'nullable|string',
        ]);
        $teamActionType->update($data);
        return $teamActionType;
    }

    public function destroy(TeamActionType $teamActionType)
    {
        $teamActionType->delete();
        return response()->noContent();
    }
}
