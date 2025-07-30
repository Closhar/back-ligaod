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
        try {
            $query = CompetitionSeason::with(['competition:id,title,title_short', 'season:id,title']);

            // Фильтр по соревнованию
            if ($request->has('competition_id')) {
                $query->where('competition_id', $request->competition_id);
            }

            // Фильтр по активности
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Поиск по названию
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $seasons = $query->orderBy('date_from', 'desc')->paginate($request->get('per_page', 15));

            return response()->json($seasons);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'competition_id' => 'required|exists:competitions,id',
                'season_id' => 'nullable|exists:seasons,id',
                'title' => 'nullable|string|max:255',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'is_active' => 'nullable',
                'description' => 'nullable|string',
            ]);

            // Преобразуем is_active в boolean
            if (isset($validated['is_active'])) {
                if (is_string($validated['is_active'])) {
                    $validated['is_active'] = in_array(strtolower($validated['is_active']), ['true', '1', 'yes', 'on']);
                } else {
                    $validated['is_active'] = (bool) $validated['is_active'];
                }
            } else {
                $validated['is_active'] = true; // По умолчанию активен
            }

            $season = CompetitionSeason::create($validated);
            $season->load(['competition:id,title,title_short', 'season:id,title']);

            return response()->json($season, 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $season = CompetitionSeason::with(['competition:id,title,title_short', 'season:id,title'])->findOrFail($id);
            return response()->json($season);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $season = CompetitionSeason::findOrFail($id);

            $validated = $request->validate([
                'competition_id' => 'sometimes|required|exists:competitions,id',
                'season_id' => 'nullable|exists:seasons,id',
                'title' => 'sometimes|nullable|string|max:255',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'is_active' => 'nullable',
                'description' => 'nullable|string',
            ]);

            // Преобразуем is_active в boolean
            if (isset($validated['is_active'])) {
                if (is_string($validated['is_active'])) {
                    $validated['is_active'] = in_array(strtolower($validated['is_active']), ['true', '1', 'yes', 'on']);
                } else {
                    $validated['is_active'] = (bool) $validated['is_active'];
                }
            }

            $season->update($validated);
            $season->load(['competition:id,title,title_short', 'season:id,title']);

            return response()->json($season);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $season = CompetitionSeason::findOrFail($id);
            $season->delete();

            return response()->json(['message' => 'Сезон успешно удален']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Получить сезоны конкретного соревнования
     */
    public function byCompetition(string $competitionId): JsonResponse
    {
        try {
            $competition = Competition::findOrFail($competitionId);
            $seasons = $competition->seasons()
                ->orderBy('date_from', 'desc')
                ->get();

            return response()->json($seasons);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Получить активные сезоны
     */
    public function active(): JsonResponse
    {
        try {
            $seasons = CompetitionSeason::with(['competition:id,title,title_short'])
                ->where('is_active', true)
                ->orderBy('date_from', 'desc')
                ->get();

            return response()->json($seasons);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
