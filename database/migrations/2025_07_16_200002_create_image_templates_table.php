<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('image_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // имя шаблона
            $table->string('type'); // horizontal, vertical, square
            $table->string('path'); // путь к шаблону
            $table->string('preview_path')->nullable(); // путь к превью
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('image_templates');
    }
};
