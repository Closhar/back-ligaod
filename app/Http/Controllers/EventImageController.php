<?php

namespace App\Http\Controllers;

use App\Models\EventImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'type' => 'nullable|string',
            'position' => 'nullable|integer',
        ]);

        if ($request->hasFile('image')) {
            $eventId = $data['event_id'];
            $file = $request->file('image');
            $dir = 'event-images/' . $eventId;
            if (!Storage::exists('public/' . $dir)) {
                Storage::makeDirectory('public/' . $dir);
            }
            $path = $file->store($dir, 'public');
            $data['path'] = '/storage/' . $path;
        } else if ($request->has('path')) {
            // На случай, если путь передан напрямую (например, url)
            $data['path'] = $request->input('path');
        } else {
            return response()->json(['error' => 'Файл изображения не передан'], 422);
        }

        if ($request->has('preview_path')) {
            $data['preview_path'] = $request->input('preview_path');
        }

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
