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
        Schema::create('rating_years', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unique();
            $table->string('title')->nullable(); // Например: "2024 год"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_calculated')->default(false); // Был ли рассчитан рейтинг за этот год
            $table->timestamp('calculated_at')->nullable(); // Когда был рассчитан рейтинг
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_years');
    }
};
