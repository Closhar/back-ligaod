<?php

namespace Database\Seeders;

use App\Models\AdminPage;
use App\Models\MenuSection;
use Illuminate\Database\Seeder;

class AdminPageSeeder extends Seeder
{
    public function run(): void
    {
        // Находим или создаем раздел "Рейтинг" для типов турниров
        $ratingSection = MenuSection::firstOrCreate(
            ['name' => 'Рейтинг'],
            [
                'name' => 'Рейтинг',
                'icon' => 'fluent:trophy-20-filled',
                'description' => 'Управление рейтингом регионов',
                'sort_order' => 5,
                'status' => true
            ]
        );

        // Создаем страницу для типов турниров
        AdminPage::firstOrCreate(
            ['slug' => 'tournament-types'],
            [
                'title' => 'Типы турниров',
                'slug' => 'tournament-types',
                'icon' => 'fluent:trophy-20-filled',
                'description' => 'Управление типами турниров и их очками для расчета рейтинга',
                'menu' => true,
                'menu_section_id' => $ratingSection->id,
                'sort_order' => 2
            ]
        );

        // Создаем страницу для достижений клубов (если её нет)
        AdminPage::firstOrCreate(
            ['slug' => 'achievements'],
            [
                'title' => 'Достижения клубов',
                'slug' => 'achievements',
                'icon' => 'fluent:star-20-filled',
                'description' => 'Управление достижениями клубов для расчета рейтинга',
                'menu' => true,
                'menu_section_id' => $ratingSection->id,
                'sort_order' => 1
            ]
        );
    }
}
