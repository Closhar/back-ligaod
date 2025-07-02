<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            [
                'name' => 'Тренер',
                'description' => 'Тренер спортивной команды или спортсмена',
                'is_active' => true,
            ],
            [
                'name' => 'Главный тренер',
                'description' => 'Главный тренер команды',
                'is_active' => true,
            ],
            [
                'name' => 'Тренер-преподаватель',
                'description' => 'Тренер-преподаватель в спортивной школе',
                'is_active' => true,
            ],
            [
                'name' => 'Старший тренер',
                'description' => 'Старший тренер команды',
                'is_active' => true,
            ],
            [
                'name' => 'Тренер по физической подготовке',
                'description' => 'Тренер по физической подготовке',
                'is_active' => true,
            ],
            [
                'name' => 'Тренер-методист',
                'description' => 'Тренер-методист',
                'is_active' => true,
            ],
            [
                'name' => 'Инструктор',
                'description' => 'Инструктор по спорту',
                'is_active' => true,
            ],
            [
                'name' => 'Судья',
                'description' => 'Судья соревнований',
                'is_active' => true,
            ],
            [
                'name' => 'Главный судья',
                'description' => 'Главный судья соревнований',
                'is_active' => true,
            ],
            [
                'name' => 'Секретарь',
                'description' => 'Секретарь соревнований',
                'is_active' => true,
            ],
            [
                'name' => 'Врач команды',
                'description' => 'Врач спортивной команды',
                'is_active' => true,
            ],
            [
                'name' => 'Массажист',
                'description' => 'Массажист команды',
                'is_active' => true,
            ],
            [
                'name' => 'Менеджер',
                'description' => 'Менеджер команды',
                'is_active' => true,
            ],
            [
                'name' => 'Директор',
                'description' => 'Директор спортивной организации',
                'is_active' => true,
            ],
            [
                'name' => 'Заместитель директора',
                'description' => 'Заместитель директора спортивной организации',
                'is_active' => true,
            ],
        ];

        foreach ($positions as $position) {
            Position::firstOrCreate(
                ['name' => $position['name']],
                $position
            );
        }
    }
}
