<?php

namespace Database\Seeders;

use App\Models\AdminPage;
use App\Models\MenuSection;
use Illuminate\Database\Seeder;

class CompetitionSeasonPageSeeder extends Seeder
{
    public function run(): void
    {
        // Находим или создаем раздел "Соревнования"
        $competitionSection = MenuSection::firstOrCreate(
            ['name' => 'Соревнования'],
            [
                'name' => 'Соревнования',
                'icon' => 'fluent:trophy-20-filled',
                'description' => 'Управление соревнованиями и их сезонами',
                'sort_order' => 3,
                'status' => true
            ]
        );

        // Создаем страницу для сезонов соревнований
        AdminPage::firstOrCreate(
            ['slug' => 'competition-seasons'],
            [
                'title' => 'Сезоны соревнований',
                'slug' => 'competition-seasons',
                'icon' => 'fluent:calendar-multiple-20-filled',
                'description' => 'Управление сезонами соревнований',
                'menu' => true,
                'menu_section_id' => $competitionSection->id,
                'sort_order' => 2
            ]
        );

        // Создаем страницу для соревнований (если её нет)
        AdminPage::firstOrCreate(
            ['slug' => 'competitions'],
            [
                'title' => 'Соревнования',
                'slug' => 'competitions',
                'icon' => 'fluent:trophy-20-filled',
                'description' => 'Управление соревнованиями',
                'menu' => true,
                'menu_section_id' => $competitionSection->id,
                'sort_order' => 1
            ]
        );
    }
}
