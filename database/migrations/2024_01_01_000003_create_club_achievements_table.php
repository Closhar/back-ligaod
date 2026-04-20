<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('club_achievements')) {
            return;
        }

        Schema::create('club_achievements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->integer('year'); // Год достижения
            $table->string('tournament_type'); // Тип турнира: championship, first_league, cup, supercup
            $table->string('division')->nullable(); // Дивизион: premier, first
            $table->integer('position'); // Место в турнире
            $table->integer('teams_count'); // Количество команд в турнире
            $table->boolean('promoted')->default(false); // Вышел в высшую лигу
            $table->decimal('points_earned', 10, 2)->default(0); // Заработанные очки
            $table->decimal('coefficient', 3, 2)->default(1.0); // Коэффициент (0.5 для фарм-клубов)
            $table->json('calculation_details')->nullable(); // Детали расчета
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index(['club_id', 'year']);
            $table->index(['tournament_type', 'year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('club_achievements');
    }
};
