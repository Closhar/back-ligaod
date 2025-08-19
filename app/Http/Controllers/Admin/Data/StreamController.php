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
        // Логируем входящий запрос для отладки
        \Log::info('StreamController index request', [
            'query_params' => $request->all(),
            'user_agent' => $request->userAgent()
        ]);

        $query = Stream::query();

        // Фильтрация по ID
        if ($request->has('id')) {
            $query->where('id', $request->input('id'));
        }

        // Фильтрация по in_main
        if ($request->has('in_main')) {
            $query->where('in_main', $request->input('in_main'));
        }

        // Фильтрация по in_player
        if ($request->has('in_player')) {
            $query->where('in_player', $request->input('in_player'));
        }

        // Фильтрация по in_profile
        if ($request->has('in_profile')) {
            $query->where('in_profile', $request->input('in_profile'));
        }

        // Сортировка
        if ($request->has('sort_by') && $request->has('sort')) {
            $sortDirection = $request->input('sort') === 'desc' ? 'desc' : 'asc';

            // Специальная обработка для сортировки по дате события
            if ($request->input('sort_by') === 'event.date_from') {
                $query->join('events', 'streams.event_id', '=', 'events.id')
                      ->orderBy('events.date_from', $sortDirection)
                      ->select('streams.*');
            } else {
                $query->orderBy($request->input('sort_by'), $sortDirection);
            }
        } else {
            // По умолчанию сортируем по дате создания
            $query->orderBy('created_at', 'desc');
        }

        // Загрузка связей
        $withRelations = [];
        if ($request->has('with')) {
            $relations = explode(',', $request->input('with'));
            foreach ($relations as $relation) {
                $relation = trim($relation);
                if (in_array($relation, ['event', 'event.competition', 'event.arena'])) {
                    $withRelations[] = $relation;
                }
            }
        }

        if (!empty($withRelations)) {
            $query->with($withRelations);
        } else {
            // По умолчанию загружаем событие
            $query->with('event');
        }

        // Retrieve streams with filtering and pagination
        $result = $query->paginate($request->input('per_page', 10));

        // Логируем результат для отладки
        \Log::info('StreamController index result', [
            'total_count' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'streams_count' => $result->count(),
            'has_in_main_true' => $result->where('in_main', true)->count()
        ]);

        return $result;
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

            $stream = Stream::create($validated);

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
