<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('telegram_channels', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('chat_id')->unique(); // ID чата в Telegram
            $table->string('type')->default('channel'); // channel или group
            $table->string('username')->nullable(); // @username для канала/группы
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('settings')->nullable(); // Дополнительные настройки
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('telegram_channels');
    }
};