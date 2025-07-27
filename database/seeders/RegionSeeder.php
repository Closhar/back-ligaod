<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем регион Санкт-Петербурга
        Region::firstOrCreate(
            ['subdomain' => 'spb'],
            [
                'title' => 'Санкт-Петербург',
                'title_short' => 'СПб',
                'subdomain' => 'spb',
            ]
        );

        // Создаем регион Москвы (если нужен)
        Region::firstOrCreate(
            ['subdomain' => 'moscow'],
            [
                'title' => 'Москва',
                'title_short' => 'Москва',
                'subdomain' => 'moscow',
            ]
        );
    }
}
