<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use App\Models\CompetitionSeason;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Контроллер для управления отдельным стримом
 */
class StreamController extends Controller
{
    public function index(Request $request)
    {
        // Логируем входящий запрос для отладки
        Log::info('StreamController index request', [
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

        // Всегда загружаем competition.sport и competition.gender для формирования title_with_season
        if (!empty($withRelations)) {
            $query->with($withRelations);
        } else {
            // По умолчанию загружаем событие с соревнованием, спортом и гендером
            $query->with(['event.competition.sport', 'event.competition.gender', 'event.arena']);
        }

        // Retrieve streams with filtering and pagination
        $result = $query->paginate($request->input('per_page', 10));

        // Формируем title_with_season для связанных событий
        if ($result->count() > 0) {
            $result->getCollection()->transform(function ($stream) {
                if ($stream->event && $stream->event->competition) {
                    $this->addTitleWithSeason($stream->event);
                }
                return $stream;
            });
        }

        // Логируем результат для отладки
        Log::info('StreamController index result', [
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
                } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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

    /**
     * Добавить title_with_season для события
     */
    private function addTitleWithSeason($event)
    {
        if (!$event->competition) {
            return;
        }

        // Определяем сезон по дате события и формируем название
        $eventDate = Carbon::parse($event->date_from);
        $seasonData = $this->getSeasonTitleForEvent($event->competition_id, $eventDate);

        // Логируем для отладки
        Log::info('StreamController: Формирование title_with_season', [
            'event_id' => $event->id,
            'competition_id' => $event->competition_id,
            'event_date' => $eventDate->format('Y-m-d'),
            'competition_title' => $event->competition->title,
            'season_data' => $seasonData,
            'final_title' => $seasonData['competition_title'] ?? $event->competition->title
        ]);

        // Определяем название соревнования
        $competitionTitle = $seasonData['competition_title'] ?? $event->competition->title;

        // Формируем финальное название
        if ($seasonData['season_title']) {
            $event->competition->title_with_season = $competitionTitle . ' ' . $seasonData['season_title'];
        } else {
            $event->competition->title_with_season = $competitionTitle;
        }
    }

    /**
     * Получить название сезона для события по дате
     */
    private function getSeasonTitleForEvent(int $competitionId, Carbon $eventDate): array
    {
        // Проверяем competition_seasons по датам - ТОЛЬКО строгая проверка по датам
        $competitionSeason = CompetitionSeason::where('competition_id', $competitionId)
            ->where('is_active', true)
            ->where(function ($query) use ($eventDate) {
                $query->where(function ($subQuery) use ($eventDate) {
                    // Случай 1: Строгая проверка интервала date_from - date_to
                    $subQuery->where('date_from', '<=', $eventDate)
                        ->where('date_to', '>=', $eventDate);
                })->orWhere(function ($subQuery) use ($eventDate) {
                    // Случай 2: Если date_to не указан, проверяем только date_from
                    $subQuery->where('date_from', '<=', $eventDate)
                        ->whereNull('date_to');
                });
            })
            ->first();

        // Ищем общий сезон - ТОЛЬКО строгая проверка по датам
        $season = Season::where('is_active', true)
            ->where(function ($query) use ($eventDate) {
                $query->where(function ($subQuery) use ($eventDate) {
                    // Случай 1: Строгая проверка интервала date_from - date_to
                    $subQuery->where('date_from', '<=', $eventDate)
                        ->where('date_to', '>=', $eventDate);
                })->orWhere(function ($subQuery) use ($eventDate) {
                    // Случай 2: Если date_to не указан, проверяем только date_from
                    $subQuery->where('date_from', '<=', $eventDate)
                        ->whereNull('date_to');
                });
            })
            ->whereHas('competitions', function ($query) use ($competitionId) {
                $query->where('competitions.id', $competitionId);
            })
            ->first();

        $competitionTitle = null;
        $seasonTitle = null;

        // Если нашли competition_season по датам
        if ($competitionSeason) {
            // Если у competition_season есть title, используем его
            // (сезон уже найден по правильным критериям, дополнительная проверка не нужна)
            if (!empty($competitionSeason->title)) {
                $competitionTitle = $competitionSeason->title;
            }
        }

        // Если нашли общий сезон, используем его title
        if ($season) {
            $seasonTitle = $season->title;
        }

        // Логируем для отладки
        Log::info('StreamController: Результат поиска сезона', [
            'competition_id' => $competitionId,
            'event_date' => $eventDate->format('Y-m-d'),
            'event_date_timestamp' => $eventDate->timestamp,
            'found_competition_season' => $competitionSeason ? [
                'id' => $competitionSeason->id,
                'title' => $competitionSeason->title,
                'date_from' => $competitionSeason->date_from,
                'date_from_timestamp' => $competitionSeason->date_from ? $competitionSeason->date_from->timestamp : null,
                'date_to' => $competitionSeason->date_to,
                'date_to_timestamp' => $competitionSeason->date_to ? $competitionSeason->date_to->timestamp : null,
                'is_in_range' => $competitionSeason->date_from && $competitionSeason->date_to
                    ? ($competitionSeason->date_from <= $eventDate && $competitionSeason->date_to >= $eventDate)
                    : 'date_to is null'
            ] : null,
            'found_season' => $season ? [
                'id' => $season->id,
                'title' => $season->title,
                'date_from' => $season->date_from,
                'date_from_timestamp' => $season->date_from ? $season->date_from->timestamp : null,
                'date_to' => $season->date_to,
                'date_to_timestamp' => $season->date_to ? $season->date_to->timestamp : null,
                'is_in_range' => $season->date_from && $season->date_to
                    ? ($season->date_from <= $eventDate && $season->date_to >= $eventDate)
                    : 'date_to is null'
            ] : null,
            'result' => [
                'competition_title' => $competitionTitle,
                'season_title' => $seasonTitle
            ]
        ]);

        return [
            'competition_title' => $competitionTitle,
            'season_title' => $seasonTitle
        ];
    }
}
