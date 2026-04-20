<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('person_club_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('club_id');
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable(); // null означает, что персона все еще в клубе
            $table->string('position')->nullable(); // должность/позиция в клубе
            $table->text('notes')->nullable(); // дополнительные заметки
            $table->timestamps();

            // Индексы
            $table->index('person_id');
            $table->index('club_id');
            $table->index('left_at');

            // Уникальный индекс для предотвращения дублирования активных членств
            $table->unique(['person_id', 'club_id', 'left_at'], 'unique_active_membership');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_club_memberships');
    }
};
