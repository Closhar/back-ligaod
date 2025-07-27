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
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique(); // Уникальное название сезона (например, "2023/2024")
            $table->string('name')->nullable(); // Альтернативное название
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Создаем таблицу связи сезонов с соревнованиями
        Schema::create('competition_seasons', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('competition_id')->unsigned();
            $table->bigInteger('season_id')->unsigned();
            $table->timestamps();

            $table->foreign('competition_id')->references('id')->on('competitions')->onDelete('cascade');
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');

            // Уникальная связь между соревнованием и сезоном
            $table->unique(['competition_id', 'season_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_seasons');
        Schema::dropIfExists('seasons');
    }
};
