<?php

namespace App\Http\Controllers;

use App\Models\EventImage;
use Illuminate\Http\Request;

class EventImageController extends Controller
{
    public function index(Request $request)
    {
        $eventId = $request->get('event_id');
        $query = EventImage::query();
        if ($eventId) {
            $query->where('event_id', $eventId);
        }
        return response()->json($query->orderBy('position')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'required|exists:events,id',
            'path' => 'required|string',
            'type' => 'nullable|string',
            'preview_path' => 'nullable|string',
            'position' => 'nullable|integer',
        ]);
        $image = EventImage::create($data);
        return response()->json($image, 201);
    }

    public function show(EventImage $eventImage)
    {
        return response()->json($eventImage);
    }

    public function update(Request $request, EventImage $eventImage)
    {
        $data = $request->validate([
            'path' => 'sometimes|required|string',
            'type' => 'nullable|string',
            'preview_path' => 'nullable|string',
            'position' => 'nullable|integer',
        ]);
        $eventImage->update($data);
        return response()->json($eventImage);
    }

    public function destroy(EventImage $eventImage)
    {
        $eventImage->delete();
        return response()->json(['success' => true]);
    }
}
