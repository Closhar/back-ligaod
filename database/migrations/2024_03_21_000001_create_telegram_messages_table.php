<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('telegram_channels')->onDelete('cascade');
            $table->bigInteger('message_id')->unsigned(); // ID сообщения в Telegram
            $table->text('content')->nullable(); // Текст сообщения
            $table->json('media')->nullable(); // Медиафайлы (фото, видео и т.д.)
            $table->timestamp('message_date'); // Дата сообщения
            $table->json('raw_data')->nullable(); // Сырые данные от Telegram API
            $table->timestamps();

            // Уникальный индекс для предотвращения дубликатов
            $table->unique(['channel_id', 'message_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('telegram_messages');
    }
};
