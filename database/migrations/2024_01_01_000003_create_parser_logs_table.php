<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parser_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_template_id')->constrained()->onDelete('cascade');
            $table->string('url'); // URL обработанной страницы
            $table->text('raw_data')->nullable(); // Сырые данные
            $table->text('parsed_data')->nullable(); // Обработанные данные
            $table->text('errors')->nullable(); // Ошибки парсинга
            $table->enum('status', ['success', 'error', 'partial']); // Статус парсинга
            $table->integer('records_created')->default(0); // Количество созданных записей
            $table->integer('records_updated')->default(0); // Количество обновленных записей
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parser_logs');
    }
};
