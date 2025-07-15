<?php
namespace App\Http\Controllers;

use App\Models\ActionType;
use Illuminate\Http\Request;

class ActionTypeController extends Controller
{
    public function index()
    {
        return ActionType::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'group' => 'nullable|string',
            'short_name' => 'nullable|string',
        ]);
        return ActionType::create($data);
    }

    public function update(Request $request, ActionType $actionType)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'group' => 'nullable|string',
            'short_name' => 'nullable|string',
        ]);
        $actionType->update($data);
        return $actionType;
    }

    public function destroy(ActionType $actionType)
    {
        $actionType->delete();
        return response()->noContent();
    }
}
