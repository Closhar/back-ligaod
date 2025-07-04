<?php

namespace Database\Seeders;

use App\Models\TournamentType;
use App\Models\TournamentTypePoint;
use Illuminate\Database\Seeder;

class TournamentTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Создаем типы турниров
        $tournamentTypes = [
            [
                'code' => 'premier_league',
                'name' => 'Высшая лига',
                'color_class' => 'bg-red-100 text-red-800',
                'sort_order' => 1,
                'points' => [
                    ['position' => 1, 'points' => 150, 'description' => '1 место - 150 очков'],
                    ['position' => 2, 'points' => 120, 'description' => '2 место - 120 очков'],
                    ['position' => 3, 'points' => 90, 'description' => '3 место - 90 очков'],
                    ['position' => 4, 'points' => 30, 'description' => '4 место - 30 очков'],
                ]
            ],
            [
                'code' => 'championship',
                'name' => 'Чемпионат',
                'color_class' => 'bg-blue-100 text-blue-800',
                'sort_order' => 2,
                'points' => [
                    ['position' => 1, 'points' => 100, 'description' => '1 место - 100 очков'],
                    ['position' => 2, 'points' => 80, 'description' => '2 место - 80 очков'],
                    ['position' => 3, 'points' => 60, 'description' => '3 место - 60 очков'],
                    ['position' => 4, 'points' => 20, 'description' => '4 место - 20 очков'],
                ]
            ],
            [
                'code' => 'first_league',
                'name' => 'Первая лига',
                'color_class' => 'bg-green-100 text-green-800',
                'sort_order' => 3,
                'points' => [
                    ['position' => 1, 'points' => 50, 'description' => '1 место - 50 очков'],
                    ['position' => 2, 'points' => 30, 'description' => '2 место - 30 очков'],
                    ['position' => 3, 'points' => 20, 'description' => '3 место - 20 очков'],
                    ['position' => 4, 'points' => 5, 'description' => '4 место - 5 очков'],
                    ['position' => 1, 'points' => 80, 'description' => '1 место + повышение - 50+30 очков', 'promoted_bonus' => true],
                    ['position' => 2, 'points' => 60, 'description' => '2 место + повышение - 30+30 очков', 'promoted_bonus' => true],
                    ['position' => 3, 'points' => 50, 'description' => '3 место + повышение - 20+30 очков', 'promoted_bonus' => true],
                    ['position' => 4, 'points' => 35, 'description' => '4 место + повышение - 5+30 очков', 'promoted_bonus' => true],
                ]
            ],
            [
                'code' => 'cup',
                'name' => 'Кубок',
                'color_class' => 'bg-yellow-100 text-yellow-800',
                'sort_order' => 4,
                'points' => [
                    ['position' => 1, 'points' => 50, 'description' => 'Победа - 50 очков'],
                    ['position' => 2, 'points' => 30, 'description' => 'Финал - 30 очков'],
                    ['position' => 3, 'points' => 20, 'description' => 'Полуфинал - 20 очков'],
                    ['position' => 4, 'points' => 20, 'description' => 'Полуфинал - 20 очков'],
                ]
            ],
            [
                'code' => 'supercup',
                'name' => 'Суперкубок',
                'color_class' => 'bg-purple-100 text-purple-800',
                'sort_order' => 5,
                'points' => [
                    ['position' => 1, 'points' => 30, 'description' => 'Победа - 30 очков'],
                    ['position' => 2, 'points' => 10, 'description' => 'Участие - 10 очков'],
                ]
            ],
        ];

        foreach ($tournamentTypes as $typeData) {
            $points = $typeData['points'];
            unset($typeData['points']);

            $tournamentType = TournamentType::create($typeData);

                        foreach ($points as $pointData) {
                $promotedBonus = $pointData['promoted_bonus'] ?? false;
                unset($pointData['promoted_bonus']);

                // Для первой лиги с бонусом за повышение добавляем специальное описание
                if ($promotedBonus) {
                    $pointData['description'] .= ' (с бонусом за повышение)';
                }

                $tournamentType->points()->create($pointData);
            }
        }
    }
}
