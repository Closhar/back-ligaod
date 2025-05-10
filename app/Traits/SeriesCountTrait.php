<?php

namespace App\Traits;

use App\Models\Event;
use App\Models\Series;

trait SeriesCountTrait
{
    protected function calculateSeriesCount(Event $event): void
    {
        if (!$event->series_id) {
            return;
        }

        $series = Series::with('seriesType')->find($event->series_id);
        if (!$series) {
            return;
        }

        $seriesEvents = Event::where('series_id', $event->series_id)
            ->select('id', 'club1_id', 'club2_id', 'result', 'result_dop')
            ->get();

        if ($series->series_type_id == 1) {
            // Тип 1: подсчет побед
            $club1Wins = 0;
            $club2Wins = 0;

            // Определяем текущие команды
            $currentClub1Id = $event->club1_id;
            $currentClub2Id = $event->club2_id;

            foreach ($seriesEvents as $seriesEvent) {
                $result = $seriesEvent->result;
                if (!$result) {
                    continue;
                }

                $scores = array_map(function($score) {
                    return (int)preg_replace('/[^0-9]/', '', $score);
                }, explode(':', $result));

                if (count($scores) !== 2) {
                    continue;
                }

                // Определяем, какая команда в текущем событии соответствует club1 и club2
                $isClub1Home = ($seriesEvent->club1_id == $currentClub1Id && $seriesEvent->club2_id == $currentClub2Id);
                $isClub1Away = ($seriesEvent->club1_id == $currentClub2Id && $seriesEvent->club2_id == $currentClub1Id);

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

            // Обновляем series_count для каждого события серии с учетом его собственных club1_id и club2_id
            foreach ($seriesEvents as $seriesEvent) {
                $isClub1Home = ($seriesEvent->club1_id == $currentClub1Id && $seriesEvent->club2_id == $currentClub2Id);
                $isClub1Away = ($seriesEvent->club1_id == $currentClub2Id && $seriesEvent->club2_id == $currentClub1Id);

                if ($isClub1Home) {
                    $seriesCount = $club1Wins . '-' . $club2Wins;
                } elseif ($isClub1Away) {
                    $seriesCount = $club2Wins . '-' . $club1Wins;
                } else {
                    continue;
                }

                $seriesEvent->update(['series_count' => $seriesCount]);
            }
        } elseif ($series->series_type_id == 2) {
            // Тип 2: подсчет голов
            $club1Goals = 0;
            $club2Goals = 0;

            // Определяем текущие команды
            $currentClub1Id = $event->club1_id;
            $currentClub2Id = $event->club2_id;

            foreach ($seriesEvents as $seriesEvent) {
                $result = $seriesEvent->result;
                if (!$result) {
                    continue;
                }

                $scores = array_map(function($score) {
                    return (int)preg_replace('/[^0-9]/', '', $score);
                }, explode(':', $result));

                if (count($scores) !== 2) {
                    continue;
                }

                // Определяем, какая команда в текущем событии соответствует club1 и club2
                $isClub1Home = ($seriesEvent->club1_id == $currentClub1Id && $seriesEvent->club2_id == $currentClub2Id);
                $isClub1Away = ($seriesEvent->club1_id == $currentClub2Id && $seriesEvent->club2_id == $currentClub1Id);

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

            // Обновляем series_count для каждого события серии с учетом его собственных club1_id и club2_id
            foreach ($seriesEvents as $seriesEvent) {
                $isClub1Home = ($seriesEvent->club1_id == $currentClub1Id && $seriesEvent->club2_id == $currentClub2Id);
                $isClub1Away = ($seriesEvent->club1_id == $currentClub2Id && $seriesEvent->club2_id == $currentClub1Id);

                if ($isClub1Home) {
                    $seriesCount = $club1Goals . ':' . $club2Goals;
                } elseif ($isClub1Away) {
                    $seriesCount = $club2Goals . ':' . $club1Goals;
                } else {
                    continue;
                }

                $seriesEvent->update(['series_count' => $seriesCount]);
            }
        }
    }
}
