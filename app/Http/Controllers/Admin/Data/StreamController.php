<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Контроллер для управления отдельным стримом
 */
class StreamController extends Controller
{
    public function index(Request $request)
    {
        $query = Stream::query();

        // Фильтрация по ID
        if ($request->has('id')) {
            $query->where('id', $request->input('id'));
        }

        // Загрузка связи с событием
        $query->with('event');

        // Retrieve streams with filtering and pagination
        return $query->paginate($request->input('per_page', 10));
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Преобразование даты из формата ISO 8601 в нужный формат
            if ($request->has('date')) {
                $dateInput = $request->input('date');
                try {
                    $date = Carbon::parse($dateInput);
                    $request->merge(['date' => $date->format('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Некорректный формат даты. Используйте ISO 8601 или YYYY-MM-DD HH:ii:ss'
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => 'Поле дата обязательно.'
                ], 422);
            }

            $validated = $request->validate([
                'date' => 'required|date_format:Y-m-d H:i:s',
                'title' => 'required|string|max:255',
                'link' => 'required|url',
                'event_id' => 'integer|exists:events,id',
                'in_player' => 'boolean',
                'in_profile' => 'boolean',
                'in_main' => 'boolean'
            ]);

            // Временная отладка валидированных данных
            \Log::info('StreamController store - валидированные данные:', $validated);

                        // Временная отладка перед созданием
            \Log::info('StreamController store - данные для создания:', $validated);

            $stream = Stream::create($validated);

            // Временная отладка созданной записи
            \Log::info('StreamController store - созданная запись:', $stream->toArray());

            // Дополнительная отладка - проверяем, что поле in_main есть в базе
            $freshStream = Stream::find($stream->id);
            \Log::info('StreamController store - свежая запись из БД:', $freshStream->toArray());

            return response()->json($stream, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $stream = Stream::findOrFail($id);
            return response()->json($stream);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not Found'], 404);
        }
    }

    /**
     * Обновить существующий стрим
     */
    public function update(Request $request, Stream $stream)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|required|date',
            'title' => 'sometimes|required|string|max:255',
            'link' => 'nullable|url|max:500',
            'event_id' => 'nullable|integer|exists:events,id',
            'in_player' => 'boolean',
            'in_profile' => 'boolean',
            'in_main' => 'boolean'
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
