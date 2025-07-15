<?php
namespace App\Http\Controllers;

use App\Models\EventAction;
use Illuminate\Http\Request;

class EventActionController extends Controller
{
    public function index($eventId)
    {
        return EventAction::where('event_id', $eventId)
            ->with(['club', 'person', 'actionType', 'relatedAction'])
            ->orderBy('minute')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'club_id' => 'required|integer|exists:clubs,id',
            'person_id' => 'nullable|integer|exists:people,id',
            'player_name' => 'nullable|string',
            'action_type_id' => 'required|integer|exists:action_types,id',
            'minute' => 'required|integer',
            'value' => 'nullable|integer',
            'related_action_id' => 'nullable|integer|exists:event_actions,id',
            'extra_info' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'score' => 'nullable|string',
        ]);
        // Если sort_order не передан, выставляем максимальный+1 среди событий этого event_id
        if (!isset($data['sort_order']) || $data['sort_order'] === null) {
            $maxSort = EventAction::where('event_id', $data['event_id'])->max('sort_order');
            $data['sort_order'] = is_null($maxSort) ? 0 : $maxSort + 1;
        }
        return EventAction::create($data);
    }

    public function update(Request $request, EventAction $eventAction)
    {
        $data = $request->validate([
            'club_id' => 'sometimes|integer|exists:clubs,id',
            'person_id' => 'nullable|integer|exists:people,id',
            'player_name' => 'nullable|string',
            'action_type_id' => 'sometimes|integer|exists:action_types,id',
            'minute' => 'sometimes|integer',
            'value' => 'nullable|integer',
            'related_action_id' => 'nullable|integer|exists:event_actions,id',
            'extra_info' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'score' => 'nullable|string',
        ]);
        $eventAction->update($data);
        return $eventAction;
    }

    public function destroy(EventAction $eventAction)
    {
        $eventAction->delete();
        return response()->noContent();
    }
}
