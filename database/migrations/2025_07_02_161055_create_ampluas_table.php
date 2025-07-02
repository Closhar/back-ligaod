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
        Schema::create('ampluas', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // название амплуа
            $table->text('description')->nullable(); // описание
            $table->boolean('is_active')->default(true); // активно ли амплуа
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
        Schema::dropIfExists('ampluas');
    }
};
