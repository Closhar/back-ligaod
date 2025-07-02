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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // название должности
            $table->text('description')->nullable(); // описание
            $table->boolean('is_active')->default(true); // активна ли должность
            $table->timestamps();

            // Индексы
            $table->index('is_active');
            $table->unique('name'); // уникальность названия
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
