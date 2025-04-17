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
        Schema::table('streams', function (Blueprint $table) {
            // Удаляем существующий внешний ключ
            $table->dropForeign('streams_event_id_foreign');

            // Создаем новый внешний ключ с другим действием при удалении
            $table->foreign('event_id')
                  ->references('id')
                  ->on('events')
                  ->onDelete('set null'); // Новое действие (set null, cascade, restrict и т.д.)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('your_table', function (Blueprint $table) {
            // Удаляем внешний ключ с новым действием
            $table->dropForeign('streams_event_id_foreign');

            // Восстанавливаем исходный внешний ключ
            $table->foreign('event_id')
                  ->references('id')
                  ->on('events')
                  ->onDelete('cascade'); // Исходное действие
        });
    }
};