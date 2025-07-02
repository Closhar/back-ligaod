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
        Schema::create('person_role_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->date('started_at'); // дата начала роли
            $table->date('ended_at')->nullable(); // дата окончания роли (null для активных)
            $table->text('notes')->nullable(); // дополнительные заметки
            $table->timestamps();

            // Индексы
            $table->index('person_id');
            $table->index('role_id');
            $table->index('ended_at');

            // Уникальный индекс для предотвращения дублирования активных ролей
            $table->unique(['person_id', 'role_id', 'ended_at'], 'unique_active_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_role_memberships');
    }
};
