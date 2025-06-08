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
        Schema::create('telegram_parse_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->unique()->comment('ID канала в Telegram');
            $table->string('username')->nullable()->index()->comment('Username канала');
            $table->string('title')->comment('Название канала в системе');
            $table->enum('parse_frequency', ['hourly', 'daily', 'weekly'])->default('daily')->comment('Периодичность парсинга');
            $table->datetime('start_date')->comment('Дата начала парсинга');
            $table->datetime('last_parse_at')->nullable()->comment('Дата последнего парсинга');
            $table->boolean('is_active')->default(true)->index()->comment('Активен ли парсинг');
            $table->integer('messages_count')->default(0)->comment('Количество собранных сообщений');
            $table->enum('parse_status', ['idle', 'running', 'error'])->default('idle')->comment('Статус парсинга');
            $table->text('error_message')->nullable()->comment('Сообщение об ошибке');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_parse_channels');
    }
};
