<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('region_ratings')) {
            return;
        }

        Schema::create('region_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_region_id')->constrained('rating_regions')->onDelete('cascade');
            $table->unsignedBigInteger('sport_id');
            $table->integer('year'); // Год рейтинга
            $table->decimal('total_points', 10, 2)->default(0); // Общее количество очков
            $table->integer('rank')->nullable(); // Место в рейтинге
            $table->json('details')->nullable(); // Детали расчета (для отладки)
            $table->timestamp('calculated_at')->nullable(); // Когда был рассчитан
            $table->timestamps();

            // Уникальный индекс для региона-спорт-год
            $table->unique(['rating_region_id', 'sport_id', 'year']);
            $table->index('sport_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('region_ratings');
    }
};
