<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tournament_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Уникальный код: championship, first_league, cup, supercup
            $table->string('name'); // Название: Чемпионат, Первая лига, Кубок, Суперкубок
            $table->string('color_class')->default('bg-gray-100 text-gray-800'); // CSS класс для цвета
            $table->boolean('is_active')->default(true); // Активен ли тип турнира
            $table->integer('sort_order')->default(0); // Порядок сортировки
            $table->timestamps();
        });

        // Добавляем внешний ключ в таблицу club_achievements
        Schema::table('club_achievements', function (Blueprint $table) {
            $table->foreignId('tournament_type_id')->nullable()->after('tournament_type')->constrained('tournament_types')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('club_achievements', function (Blueprint $table) {
            $table->dropForeign(['tournament_type_id']);
            $table->dropColumn('tournament_type_id');
        });

        Schema::dropIfExists('tournament_types');
    }
};
