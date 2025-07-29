<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Match;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    /**
     * Получить детали матча
     */
    public function show($id): JsonResponse
    {
        try {
            $match = Event::with([
                'home_team',
                'away_team',
                'competition',
                'arena',
                'sport',
                'events' => function ($query) {
                    $query->orderBy('minute', 'asc');
                }
            ])->findOrFail($id);

            // Формируем статистику матча (заглушка)
            $statistics = $this->getMatchStatistics($match);

            // Формируем составы команд (заглушка)
            $homeLineup = $this->getTeamLineup($match->home_team_id);
            $awayLineup = $this->getTeamLineup($match->away_team_id);

            $matchData = [
                'id' => $match->id,
                'title' => $match->title,
                'date' => $match->date,
                'status' => $this->getMatchStatus($match),
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
                'home_team' => [
                    'id' => $match->home_team->id,
                    'title' => $match->home_team->title,
                    'image' => $match->home_team->club_image_path,
                ],
                'away_team' => [
                    'id' => $match->away_team->id,
                    'title' => $match->away_team->title,
                    'image' => $match->away_team->club_image_path,
                ],
                'competition' => $match->competition ? [
                    'id' => $match->competition->id,
                    'title' => $match->competition->title,
                ] : null,
                'arena' => $match->arena ? [
                    'id' => $match->arena->id,
                    'title' => $match->arena->title,
                ] : null,
                'sport' => $match->sport ? [
                    'id' => $match->sport->id,
                    'title' => $match->sport->title,
                ] : null,
                'statistics' => $statistics,
                'events' => $match->events->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'minute' => $event->minute,
                        'type' => $event->type,
                        'description' => $event->description,
                    ];
                }),
                'home_lineup' => $homeLineup,
                'away_lineup' => $awayLineup,
                'referee' => $match->referee,
                'attendance' => $match->attendance,
                'weather' => $match->weather,
                'description' => $match->description,
            ];

            return response()->json($matchData);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Матч не найден',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Получить похожие матчи
     */
    public function similar($id): JsonResponse
    {
        try {
            $match = Event::findOrFail($id);

            // Находим похожие матчи (те же команды или тот же турнир)
            $similarMatches = Event::with(['home_team', 'away_team'])
                ->where('id', '!=', $id)
                ->where(function ($query) use ($match) {
                    $query->where(function ($q) use ($match) {
                        $q->where('home_team_id', $match->home_team_id)
                          ->where('away_team_id', $match->away_team_id);
                    })->orWhere(function ($q) use ($match) {
                        $q->where('home_team_id', $match->away_team_id)
                          ->where('away_team_id', $match->home_team_id);
                    })->orWhere('competition_id', $match->competition_id);
                })
                ->orderBy('date', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($similarMatch) {
                    return [
                        'id' => $similarMatch->id,
                        'date' => $similarMatch->date,
                        'home_team' => [
                            'id' => $similarMatch->home_team->id,
                            'title' => $similarMatch->home_team->title,
                        ],
                        'away_team' => [
                            'id' => $similarMatch->away_team->id,
                            'title' => $similarMatch->away_team->title,
                        ],
                        'home_score' => $similarMatch->home_score,
                        'away_score' => $similarMatch->away_score,
                    ];
                });

            return response()->json($similarMatches);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ошибка при поиске похожих матчей',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Определить статус матча
     */
    private function getMatchStatus($match): string
    {
        $now = now();
        $matchDate = \Carbon\Carbon::parse($match->date);

        if ($match->home_score !== null && $match->away_score !== null) {
            return 'finished';
        }

        if ($matchDate->isPast()) {
            return 'finished';
        }

        if ($matchDate->diffInMinutes($now) <= 120 && $matchDate->isFuture()) {
            return 'live';
        }

        return 'scheduled';
    }

    /**
     * Получить статистику матча (заглушка)
     */
    private function getMatchStatistics($match): array
    {
        // В реальном приложении здесь была бы логика получения статистики
        // Пока возвращаем заглушку
        return [
            'possession' => [
                'home' => rand(40, 60),
                'away' => rand(40, 60)
            ],
            'shots' => [
                'home' => rand(5, 15),
                'away' => rand(5, 15)
            ],
            'shots_on_target' => [
                'home' => rand(2, 8),
                'away' => rand(2, 8)
            ],
            'corners' => [
                'home' => rand(3, 10),
                'away' => rand(3, 10)
            ],
            'fouls' => [
                'home' => rand(8, 20),
                'away' => rand(8, 20)
            ],
            'yellow_cards' => [
                'home' => rand(0, 5),
                'away' => rand(0, 5)
            ],
            'red_cards' => [
                'home' => rand(0, 2),
                'away' => rand(0, 2)
            ],
            'offsides' => [
                'home' => rand(1, 8),
                'away' => rand(1, 8)
            ],
        ];
    }

    /**
     * Получить состав команды (заглушка)
     */
    private function getTeamLineup($teamId): array
    {
        // В реальном приложении здесь была бы логика получения состава
        // Пока возвращаем заглушку
        $positions = ['Вратарь', 'Защитник', 'Полузащитник', 'Нападающий'];
        $lineup = [];

        for ($i = 1; $i <= 11; $i++) {
            $lineup[] = [
                'id' => $i,
                'number' => $i,
                'name' => 'Игрок ' . $i,
                'position' => $positions[array_rand($positions)]
            ];
        }

        return $lineup;
    }
}
