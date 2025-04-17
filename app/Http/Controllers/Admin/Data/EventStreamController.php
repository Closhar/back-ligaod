<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventStreamController extends Controller
{
    /**
     * Получить список всех стримов для конкретного события
     */
    public function index(Request $request, Event $event)
    {
        $streams = $event->streams()
            ->orderBy('date', 'desc')
            ->get();

        return response()->json($streams);
    }

    /**
     * Создать новый стрим для конкретного события
     */
    public function store(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'link' => 'nullable|url|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $stream = $event->streams()->create($validator->validated());

        return response()->json($stream, 201);
    }
}

/**
 * Контроллер для управления отдельным стримом
 */
class StreamController extends Controller
{
    /**
     * Обновить существующий стрим
     */
    public function update(Request $request, Stream $stream)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|required|date',
            'title' => 'sometimes|required|string|max:255',
            'link' => 'nullable|url|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $stream->update($validator->validated());

        return response()->json($stream);
    }

    /**
     * Удалить стрим
     */
    public function destroy(Stream $stream)
    {
        $stream->delete();

        return response()->json(null, 204);
    }
}