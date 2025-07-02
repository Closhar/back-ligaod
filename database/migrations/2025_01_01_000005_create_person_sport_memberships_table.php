<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('person_sport_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->onDelete('cascade');
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable(); // null означает, что персона все еще занимается этим видом спорта
            $table->string('level')->nullable(); // уровень в спорте (любитель, профессионал, мастер спорта и т.д.)
            $table->text('achievements')->nullable(); // достижения в спорте
            $table->timestamps();

            // Индексы
            $table->index('person_id');
            $table->index('sport_id');
            $table->index('ended_at');

            // Уникальный индекс для предотвращения дублирования активных членств
            $table->unique(['person_id', 'sport_id', 'ended_at'], 'unique_active_sport_membership');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_sport_memberships');
    }
};
