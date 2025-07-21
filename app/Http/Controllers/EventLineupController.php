<?php
namespace App\Http\Controllers;

use App\Models\EventLineup;
use Illuminate\Http\Request;

class EventLineupController extends Controller
{
    public function index($eventId, Request $request)
    {
        $query = EventLineup::where('event_id', $eventId)
            ->with(['club', 'person', 'substitutions'])
            ->orderBy('sort_order');

        // Фильтруем по club_id, если параметр передан
        if ($request->has('club_id')) {
            $query->where('club_id', $request->club_id);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'club_id' => 'required|integer|exists:clubs,id',
            'person_id' => 'nullable|integer|exists:people,id',
            'player_name' => 'nullable|string',
            'number' => 'nullable|integer',
            'parent_lineup_id' => 'nullable|integer|exists:event_lineups,id',
            'minute_in' => 'nullable|integer',
            'minute_out' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'is_captain' => 'nullable|boolean',
        ]);
        return EventLineup::create($data);
    }

    public function update(Request $request, EventLineup $eventLineup)
    {
        $data = $request->validate([
            'club_id' => 'sometimes|integer|exists:clubs,id',
            'person_id' => 'nullable|integer|exists:people,id',
            'player_name' => 'nullable|string',
            'number' => 'nullable|integer',
            'parent_lineup_id' => 'nullable|integer|exists:event_lineups,id',
            'minute_in' => 'nullable|integer',
            'minute_out' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'is_captain' => 'nullable|boolean',
        ]);
        $eventLineup->update($data);
        return $eventLineup;
    }

    public function destroy(EventLineup $eventLineup)
    {
        $eventLineup->delete();
        return response()->noContent();
    }
}
