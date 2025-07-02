<?php

namespace Database\Seeders;

use App\Models\Amplua;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AmpluaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ampluas = [
            // Футбол
            [
                'name' => 'Вратарь',
                'description' => 'Вратарь в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Защитник',
                'description' => 'Защитник в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Полузащитник',
                'description' => 'Полузащитник в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Нападающий',
                'description' => 'Нападающий в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Центральный защитник',
                'description' => 'Центральный защитник в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Боковой защитник',
                'description' => 'Боковой защитник в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Опорный полузащитник',
                'description' => 'Опорный полузащитник в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Атакующий полузащитник',
                'description' => 'Атакующий полузащитник в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Центральный нападающий',
                'description' => 'Центральный нападающий в футболе',
                'is_active' => true,
            ],
            [
                'name' => 'Крайний нападающий',
                'description' => 'Крайний нападающий в футболе',
                'is_active' => true,
            ],

            // Хоккей
            [
                'name' => 'Вратарь (хоккей)',
                'description' => 'Вратарь в хоккее',
                'is_active' => true,
            ],
            [
                'name' => 'Защитник (хоккей)',
                'description' => 'Защитник в хоккее',
                'is_active' => true,
            ],
            [
                'name' => 'Нападающий (хоккей)',
                'description' => 'Нападающий в хоккее',
                'is_active' => true,
            ],
            [
                'name' => 'Центральный нападающий (хоккей)',
                'description' => 'Центральный нападающий в хоккее',
                'is_active' => true,
            ],
            [
                'name' => 'Крайний нападающий (хоккей)',
                'description' => 'Крайний нападающий в хоккее',
                'is_active' => true,
            ],

            // Баскетбол
            [
                'name' => 'Разыгрывающий защитник',
                'description' => 'Разыгрывающий защитник в баскетболе',
                'is_active' => true,
            ],
            [
                'name' => 'Атакующий защитник',
                'description' => 'Атакующий защитник в баскетболе',
                'is_active' => true,
            ],
            [
                'name' => 'Лёгкий форвард',
                'description' => 'Лёгкий форвард в баскетболе',
                'is_active' => true,
            ],
            [
                'name' => 'Тяжёлый форвард',
                'description' => 'Тяжёлый форвард в баскетболе',
                'is_active' => true,
            ],
            [
                'name' => 'Центровой',
                'description' => 'Центровой в баскетболе',
                'is_active' => true,
            ],

            // Волейбол
            [
                'name' => 'Связующий',
                'description' => 'Связующий в волейболе',
                'is_active' => true,
            ],
            [
                'name' => 'Доигровщик',
                'description' => 'Доигровщик в волейболе',
                'is_active' => true,
            ],
            [
                'name' => 'Центральный блокирующий',
                'description' => 'Центральный блокирующий в волейболе',
                'is_active' => true,
            ],
            [
                'name' => 'Диагональный',
                'description' => 'Диагональный в волейболе',
                'is_active' => true,
            ],
            [
                'name' => 'Либеро',
                'description' => 'Либеро в волейболе',
                'is_active' => true,
            ],

            // Общие амплуа
            [
                'name' => 'Капитан команды',
                'description' => 'Капитан спортивной команды',
                'is_active' => true,
            ],
            [
                'name' => 'Вице-капитан',
                'description' => 'Вице-капитан команды',
                'is_active' => true,
            ],
        ];

        foreach ($ampluas as $amplua) {
            Amplua::firstOrCreate(
                ['name' => $amplua['name']],
                $amplua
            );
        }
    }
}
