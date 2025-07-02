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
        Schema::create('person_position_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->onDelete('cascade');
            $table->foreignId('position_id')->constrained()->onDelete('cascade');
            $table->timestamp('started_at')->nullable(); // когда начал занимать должность
            $table->timestamp('ended_at')->nullable(); // когда закончил занимать должность
            $table->text('notes')->nullable(); // заметки
            $table->timestamps();

            // Индексы
            $table->index(['person_id', 'position_id']);
            $table->index('started_at');
            $table->index('ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_position_memberships');
    }
};
