<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parser_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название шаблона
            $table->text('description')->nullable(); // Описание шаблона
            $table->string('url_pattern'); // Паттерн URL для применения шаблона
            $table->text('conditions')->nullable(); // Условия применения (JSON)
            $table->boolean('is_active')->default(true); // Активен ли шаблон
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parser_templates');
    }
};
