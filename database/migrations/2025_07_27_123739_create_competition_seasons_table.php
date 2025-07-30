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
        Schema::create('competition_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->onDelete('cascade');
            $table->string('title')->nullable(); // Название сезона (например "2024/2025")
            $table->date('date_from')->nullable(); // Дата начала сезона
            $table->date('date_to')->nullable(); // Дата окончания сезона
            $table->boolean('is_active')->default(true); // Активный ли сезон
            $table->text('description')->nullable(); // Описание сезона
            $table->timestamps();

            // Индексы для оптимизации запросов
            $table->index(['competition_id', 'is_active']);
            $table->index(['date_from', 'date_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_seasons');
    }
};
