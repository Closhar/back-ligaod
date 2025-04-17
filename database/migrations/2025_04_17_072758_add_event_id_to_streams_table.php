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
        Schema::table('streams', function (Blueprint $table) {
            // Добавляем поле event_id как внешний ключ
            $table->unsignedBigInteger('event_id')->nullable()->after('id');

            // Добавляем внешний ключ с каскадным удалением
            // Когда удаляется событие, удаляются и все его стримы
            $table->foreign('event_id')
                  ->references('id')
                  ->on('events')
                  ->onDelete('set null');

            // Создаем индекс для ускорения запросов
            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            // Удаляем внешний ключ
            $table->dropForeign(['event_id']);

            // Удаляем индекс
            $table->dropIndex(['event_id']);

            // Удаляем столбец
            $table->dropColumn('event_id');
        });
    }
};