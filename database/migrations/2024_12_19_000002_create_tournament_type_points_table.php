<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tournament_type_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_type_id')->constrained('tournament_types')->onDelete('cascade');
            $table->integer('position'); // Место в турнире
            $table->integer('points'); // Очки за это место
            $table->integer('min_teams')->nullable(); // Минимальное количество команд для применения
            $table->integer('max_teams')->nullable(); // Максимальное количество команд для применения
            $table->boolean('is_active')->default(true); // Активна ли запись
            $table->text('description')->nullable(); // Описание условия
            $table->timestamps();

            // Уникальный индекс для предотвращения дублирования (без min_teams и max_teams, так как они могут быть null)
            $table->unique(['tournament_type_id', 'position'], 'unique_tournament_points');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tournament_type_points');
    }
};
