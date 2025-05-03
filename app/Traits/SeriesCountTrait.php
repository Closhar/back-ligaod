<?php

namespace App\Traits;

use App\Models\Event;
use App\Models\Series;

trait SeriesCountTrait
{
    protected function calculateSeriesCount(Event $event): void
    {
        if (!$event->series_id) {
            \Log::info('No series_id for event ' . $event->id);
            return;
        }

        \Log::info('Calculating series_count for event ' . $event->id . ' with series_id ' . $event->series_id);

        $series = Series::with('seriesType')->find($event->series_id);
        if (!$series) {
            \Log::error('Series not found for id ' . $event->series_id);
            return;
        }

        $seriesEvents = Event::where('series_id', $event->series_id)->get();
        \Log::info('Found ' . $seriesEvents->count() . ' events in series');

        $seriesCount = null;

        if ($series->series_type_id == 1) {
            // Тип 1: подсчет побед
            $club1Wins = 0;
            $club2Wins = 0;

            // Определяем текущие команды
            $currentClub1Id = $event->club1_id;
            $currentClub2Id = $event->club2_id;

            \Log::info('Calculating wins for clubs: ' . $currentClub1Id . ' vs ' . $currentClub2Id);

            foreach ($seriesEvents as $seriesEvent) {
                // Определяем, какая команда в текущем событии соответствует club1 и club2
                $isClub1Home = ($seriesEvent->club1_id == $currentClub1Id && $seriesEvent->club2_id == $currentClub2Id);
                $isClub1Away = ($seriesEvent->club1_id == $currentClub2Id && $seriesEvent->club2_id == $currentClub1Id);

                // Обработка основного результата
                $result = $seriesEvent->result;
                if ($result) {
                    $scores = array_map(function($score) {
                        return (int)preg_replace('/[^0-9]/', '', $score);
                    }, explode(':', $result));

                    if (count($scores) === 2) {
                        if ($isClub1Home) {
                            // Если команды в том же порядке
                            if ($scores[0] > $scores[1]) {
                                $club1Wins++;
                            } elseif ($scores[0] < $scores[1]) {
                                $club2Wins++;
                            } else {
                                // Если основной счет равен, проверяем дополнительный
                                $dopResult = $seriesEvent->result_dop;
                                if ($dopResult) {
                                    $dopScores = array_map(function($score) {
                                        return (int)preg_replace('/[^0-9]/', '', $score);
                                    }, explode(':', $dopResult));

                                    if (count($dopScores) === 2) {
                                        if ($dopScores[0] > $dopScores[1]) {
                                            $club1Wins++;
                                        } elseif ($dopScores[0] < $dopScores[1]) {
                                            $club2Wins++;
                                        }
                                    }
                                }
                            }
                        } elseif ($isClub1Away) {
                            // Если команды поменялись местами
                            if ($scores[0] < $scores[1]) {
                                $club1Wins++;
                            } elseif ($scores[0] > $scores[1]) {
                                $club2Wins++;
                            } else {
                                // Если основной счет равен, проверяем дополнительный
                                $dopResult = $seriesEvent->result_dop;
                                if ($dopResult) {
                                    $dopScores = array_map(function($score) {
                                        return (int)preg_replace('/[^0-9]/', '', $score);
                                    }, explode(':', $dopResult));

                                    if (count($dopScores) === 2) {
                                        if ($dopScores[0] < $dopScores[1]) {
                                            $club1Wins++;
                                        } elseif ($dopScores[0] > $dopScores[1]) {
                                            $club2Wins++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $seriesCount = $club1Wins . '-' . $club2Wins;
            \Log::info('Calculated series count (wins): ' . $seriesCount);
        } elseif ($series->series_type_id == 2) {
            // Тип 2: подсчет голов
            $club1Goals = 0;
            $club2Goals = 0;

            // Определяем текущие команды
            $currentClub1Id = $event->club1_id;
            $currentClub2Id = $event->club2_id;

            \Log::info('Calculating goals for clubs: ' . $currentClub1Id . ' vs ' . $currentClub2Id);

            foreach ($seriesEvents as $seriesEvent) {
                // Определяем, какая команда в текущем событии соответствует club1 и club2
                $isClub1Home = ($seriesEvent->club1_id == $currentClub1Id && $seriesEvent->club2_id == $currentClub2Id);
                $isClub1Away = ($seriesEvent->club1_id == $currentClub2Id && $seriesEvent->club2_id == $currentClub1Id);

                // Обработка основного результата
                $result = $seriesEvent->result;
                if ($result) {
                    $scores = array_map(function($score) {
                        return (int)preg_replace('/[^0-9]/', '', $score);
                    }, explode(':', $result));

                    if (count($scores) === 2) {
                        if ($isClub1Home) {
                            // Если команды в том же порядке
                            $club1Goals += $scores[0];
                            $club2Goals += $scores[1];
                        } elseif ($isClub1Away) {
                            // Если команды поменялись местами
                            $club1Goals += $scores[1];
                            $club2Goals += $scores[0];
                        }
                    }
                }
            }

            $seriesCount = $club1Goals . ':' . $club2Goals;
            \Log::info('Calculated series count (goals): ' . $seriesCount);
        }

        // Обновляем series_count для всех событий серии
        Event::where('series_id', $event->series_id)->update(['series_count' => $seriesCount]);
        \Log::info('Updated series_count for all events in series ' . $event->series_id . ' to ' . $seriesCount);
    }
}
