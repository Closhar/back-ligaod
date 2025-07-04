<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавляем высшую лигу
        $premierLeague = \App\Models\TournamentType::create([
            'code' => 'premier_league',
            'name' => 'Высшая лига',
            'color_class' => 'bg-red-100 text-red-800',
            'is_active' => true,
            'sort_order' => 1
        ]);

        // Создаем очки для высшей лиги
        $premierLeaguePoints = [
            ['position' => 1, 'points' => 150, 'description' => '1 место - 150 очков'],
            ['position' => 2, 'points' => 120, 'description' => '2 место - 120 очков'],
            ['position' => 3, 'points' => 90, 'description' => '3 место - 90 очков'],
            ['position' => 4, 'points' => 30, 'description' => '4 место - 30 очков'],
        ];

        foreach ($premierLeaguePoints as $pointData) {
            $premierLeague->points()->create($pointData);
        }

        // Обновляем порядок сортировки существующих типов
        \App\Models\TournamentType::where('code', 'championship')->update(['sort_order' => 2]);
        \App\Models\TournamentType::where('code', 'first_league')->update(['sort_order' => 3]);
        \App\Models\TournamentType::where('code', 'cup')->update(['sort_order' => 4]);
        \App\Models\TournamentType::where('code', 'supercup')->update(['sort_order' => 5]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем высшую лигу и её очки
        $premierLeague = \App\Models\TournamentType::where('code', 'premier_league')->first();
        if ($premierLeague) {
            $premierLeague->points()->delete();
            $premierLeague->delete();
        }

        // Возвращаем старый порядок сортировки
        \App\Models\TournamentType::where('code', 'championship')->update(['sort_order' => 1]);
        \App\Models\TournamentType::where('code', 'first_league')->update(['sort_order' => 2]);
        \App\Models\TournamentType::where('code', 'cup')->update(['sort_order' => 3]);
        \App\Models\TournamentType::where('code', 'supercup')->update(['sort_order' => 4]);
    }
};
