<?php
namespace App\Http\Controllers;

use App\Models\EventTeamAction;
use Illuminate\Http\Request;

class EventTeamActionController extends Controller
{
    public function index(Request $request)
    {
        $eventId = $request->query('event_id');
        $query = EventTeamAction::query();
        if ($eventId) {
            $query->where('event_id', $eventId);
        }
        return $query->with('teamActionType')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'team_action_type_id' => 'required|integer|exists:team_action_types,id',
            'value_home' => 'required|numeric',
            'value_away' => 'required|numeric',
        ]);
        return EventTeamAction::create($data);
    }

    public function update(Request $request, EventTeamAction $eventTeamAction)
    {
        $data = $request->validate([
            'team_action_type_id' => 'sometimes|integer|exists:team_action_types,id',
            'value_home' => 'sometimes|numeric',
            'value_away' => 'sometimes|numeric',
        ]);
        $eventTeamAction->update($data);
        return $eventTeamAction;
    }

    public function destroy(EventTeamAction $eventTeamAction)
    {
        $eventTeamAction->delete();
        return response()->noContent();
    }
}
