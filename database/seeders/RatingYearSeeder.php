<?php

namespace Database\Seeders;

use App\Models\RatingYear;
use Illuminate\Database\Seeder;

class RatingYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем годы от 2020 до текущего
        $currentYear = date('Y');
        for ($year = 2020; $year <= $currentYear; $year++) {
            RatingYear::createOrUpdateYear($year, [
                'title' => $year . ' год',
                'description' => 'Рейтинг за ' . $year . ' год',
                'is_active' => true,
                'is_calculated' => false
            ]);
        }
    }
}
