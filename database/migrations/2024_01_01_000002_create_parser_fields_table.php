<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parser_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_template_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Название поля
            $table->string('selector'); // CSS/XPath селектор
            $table->string('selector_type')->default('css'); // Тип селектора (css, xpath)
            $table->string('data_type')->default('text'); // Тип данных (text, number, date, etc.)
            $table->text('extraction_rules')->nullable(); // Правила извлечения (JSON)
            $table->string('target_table')->nullable(); // Целевая таблица БД
            $table->string('target_field')->nullable(); // Целевое поле БД
            $table->string('update_strategy')->default('update'); // Стратегия обновления (insert, update, upsert)
            $table->boolean('is_required')->default(false); // Обязательное поле
            $table->integer('order')->default(0); // Порядок обработки
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parser_fields');
    }
};
