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
        Schema::table('telegram_parse_channels', function (Blueprint $table) {
            // Удаляем ненужные поля
            $table->dropColumn([
                'parse_frequency',
                'start_date',
                'last_parse_at',
                'messages_count',
                'parse_status',
                'error_message'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_parse_channels', function (Blueprint $table) {
            // Возвращаем удаленные поля
            $table->enum('parse_frequency', ['hourly', 'daily', 'weekly'])->default('daily')->comment('Периодичность парсинга');
            $table->datetime('start_date')->comment('Дата начала парсинга');
            $table->datetime('last_parse_at')->nullable()->comment('Дата последнего парсинга');
            $table->integer('messages_count')->default(0)->comment('Количество собранных сообщений');
            $table->enum('parse_status', ['idle', 'running', 'error'])->default('idle')->comment('Статус парсинга');
            $table->text('error_message')->nullable()->comment('Сообщение об ошибке');
        });
    }
};
