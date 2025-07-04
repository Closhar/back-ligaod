<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Сначала создаем типы турниров, если их еще нет
        $tournamentTypes = [
            [
                'id' => 1,
                'code' => 'championship',
                'name' => 'Чемпионат',
                'color_class' => 'bg-blue-100 text-blue-800',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 2,
                'code' => 'first_league',
                'name' => 'Первая лига',
                'color_class' => 'bg-green-100 text-green-800',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 3,
                'code' => 'cup',
                'name' => 'Кубок',
                'color_class' => 'bg-yellow-100 text-yellow-800',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 4,
                'code' => 'supercup',
                'name' => 'Суперкубок',
                'color_class' => 'bg-purple-100 text-purple-800',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Вставляем типы турниров, игнорируя дубликаты
        foreach ($tournamentTypes as $type) {
            DB::table('tournament_types')->insertOrIgnore($type);
        }

        // Создаем точки для турниров
        $tournamentPoints = [
            // Чемпионат
            ['tournament_type_id' => 1, 'position' => 1, 'points' => 100, 'description' => '1 место - 100 очков'],
            ['tournament_type_id' => 1, 'position' => 2, 'points' => 80, 'description' => '2 место - 80 очков'],
            ['tournament_type_id' => 1, 'position' => 3, 'points' => 60, 'description' => '3 место - 60 очков'],
            ['tournament_type_id' => 1, 'position' => 4, 'points' => 20, 'description' => '4 место - 20 очков'],

            // Первая лига
            ['tournament_type_id' => 2, 'position' => 1, 'points' => 50, 'description' => '1 место - 50 очков'],
            ['tournament_type_id' => 2, 'position' => 2, 'points' => 30, 'description' => '2 место - 30 очков'],
            ['tournament_type_id' => 2, 'position' => 3, 'points' => 20, 'description' => '3 место - 20 очков'],
            ['tournament_type_id' => 2, 'position' => 4, 'points' => 5, 'description' => '4 место - 5 очков'],
            ['tournament_type_id' => 2, 'position' => 1, 'points' => 80, 'description' => '1 место + повышение - 50+30 очков (с бонусом за повышение)'],
            ['tournament_type_id' => 2, 'position' => 2, 'points' => 60, 'description' => '2 место + повышение - 30+30 очков (с бонусом за повышение)'],
            ['tournament_type_id' => 2, 'position' => 3, 'points' => 50, 'description' => '3 место + повышение - 20+30 очков (с бонусом за повышение)'],
            ['tournament_type_id' => 2, 'position' => 4, 'points' => 35, 'description' => '4 место + повышение - 5+30 очков (с бонусом за повышение)'],

            // Кубок
            ['tournament_type_id' => 3, 'position' => 1, 'points' => 50, 'description' => 'Победа - 50 очков'],
            ['tournament_type_id' => 3, 'position' => 2, 'points' => 30, 'description' => 'Финал - 30 очков'],
            ['tournament_type_id' => 3, 'position' => 3, 'points' => 20, 'description' => 'Полуфинал - 20 очков'],
            ['tournament_type_id' => 3, 'position' => 4, 'points' => 20, 'description' => 'Полуфинал - 20 очков'],

            // Суперкубок
            ['tournament_type_id' => 4, 'position' => 1, 'points' => 30, 'description' => 'Победа - 30 очков'],
            ['tournament_type_id' => 4, 'position' => 2, 'points' => 10, 'description' => 'Участие - 10 очков'],
        ];

        foreach ($tournamentPoints as $point) {
            DB::table('tournament_type_points')->insertOrIgnore($point);
        }

        // Обновляем существующие записи, связывая их с новыми типами турниров
        $tournamentTypeMap = [
            'championship' => 1,
            'first_league' => 2,
            'cup' => 3,
            'supercup' => 4
        ];

        foreach ($tournamentTypeMap as $oldType => $newTypeId) {
            DB::table('club_achievements')
                ->where('tournament_type', $oldType)
                ->update(['tournament_type_id' => $newTypeId]);
        }
    }

    public function down()
    {
        // Удаляем связь с типами турниров
        DB::table('club_achievements')
            ->whereNotNull('tournament_type_id')
            ->update(['tournament_type_id' => null]);
    }
};
