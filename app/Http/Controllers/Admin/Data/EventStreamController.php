<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Stream;
use Illuminate\Http\Request;

// app/Http/Controllers/EventStreamController.php
class EventStreamController extends Controller
{
    // Get all streams for an event
    public function index(Event $event)
    {
        return response()->json($event->streams);
    }

    // Create a new stream for an event
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'link' => 'nullable|url'
        ]);

        $stream = $event->streams()->create($validated);

        return response()->json($stream, 201);
    }

    // Update a stream
    public function update(Request $request, Stream $stream)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'link' => 'nullable|url'
        ]);

        $stream->update($validated);

        return response()->json($stream);
    }

    // Delete a stream
    public function destroy(Stream $stream)
    {
        $stream->delete();

        return response()->json(null, 204);
    }
}