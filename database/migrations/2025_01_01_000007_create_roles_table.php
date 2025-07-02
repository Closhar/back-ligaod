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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // название амплуа или должности
            $table->enum('type', ['sportsman', 'non_sportsman']); // тип: спортсмен или не спортсмен
            $table->text('description')->nullable(); // описание
            $table->boolean('is_active')->default(true); // активна ли роль
            $table->timestamps();

            // Индексы
            $table->index('type');
            $table->index('is_active');
            $table->unique(['name', 'type']); // уникальность названия в рамках типа
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
