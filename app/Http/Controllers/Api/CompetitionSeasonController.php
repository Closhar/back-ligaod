<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompetitionSeasonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CompetitionSeason::with(['competition:id,title,title_short']);

        // Фильтр по соревнованию
        if ($request->has('competition_id')) {
            $query->where('competition_id', $request->competition_id);
        }

        // Фильтр по активности
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Фильтр по датам
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->inDateRange($request->date_from, $request->date_to);
        }

        // Поиск по названию
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $seasons = $query->orderBy('date_from', 'desc')->paginate($request->get('per_page', 15));

        return response()->json($seasons);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competition_id' => 'required|exists:competitions,id',
            'title' => 'required|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        // Проверка на пересечение дат с другими сезонами
        if ($validated['date_from'] && $validated['date_to']) {
            $overlapping = CompetitionSeason::where('competition_id', $validated['competition_id'])
                ->where('id', '!=', $request->id ?? 0)
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('date_from', [$validated['date_from'], $validated['date_to']])
                          ->orWhereBetween('date_to', [$validated['date_from'], $validated['date_to']])
                          ->orWhere(function ($subQuery) use ($validated) {
                              $subQuery->where('date_from', '<=', $validated['date_from'])
                                       ->where('date_to', '>=', $validated['date_to']);
                          });
                })
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages([
                    'date_from' => 'Период сезона пересекается с существующим сезоном.',
                    'date_to' => 'Период сезона пересекается с существующим сезоном.',
                ]);
            }
        }

        $season = CompetitionSeason::create($validated);
        $season->load('competition:id,title,title_short');

        return response()->json($season, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $season = CompetitionSeason::with(['competition:id,title,title_short'])->findOrFail($id);
        return response()->json($season);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $season = CompetitionSeason::findOrFail($id);

        $validated = $request->validate([
            'competition_id' => 'sometimes|required|exists:competitions,id',
            'title' => 'sometimes|required|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);

        // Проверка на пересечение дат с другими сезонами
        if (isset($validated['date_from']) && isset($validated['date_to'])) {
            $overlapping = CompetitionSeason::where('competition_id', $validated['competition_id'] ?? $season->competition_id)
                ->where('id', '!=', $id)
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('date_from', [$validated['date_from'], $validated['date_to']])
                          ->orWhereBetween('date_to', [$validated['date_from'], $validated['date_to']])
                          ->orWhere(function ($subQuery) use ($validated) {
                              $subQuery->where('date_from', '<=', $validated['date_from'])
                                       ->where('date_to', '>=', $validated['date_to']);
                          });
                })
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages([
                    'date_from' => 'Период сезона пересекается с существующим сезоном.',
                    'date_to' => 'Период сезона пересекается с существующим сезоном.',
                ]);
            }
        }

        $season->update($validated);
        $season->load('competition:id,title,title_short');

        return response()->json($season);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $season = CompetitionSeason::findOrFail($id);
        $season->delete();

        return response()->json(['message' => 'Сезон успешно удален']);
    }

    /**
     * Получить сезоны конкретного соревнования
     */
    public function byCompetition(string $competitionId): JsonResponse
    {
        $competition = Competition::findOrFail($competitionId);
        $seasons = $competition->seasons()
            ->orderBy('date_from', 'desc')
            ->get();

        return response()->json($seasons);
    }

    /**
     * Получить активные сезоны
     */
    public function active(): JsonResponse
    {
        $seasons = CompetitionSeason::with(['competition:id,title,title_short'])
            ->where('is_active', true)
            ->orderBy('date_from', 'desc')
            ->get();

        return response()->json($seasons);
    }
}
