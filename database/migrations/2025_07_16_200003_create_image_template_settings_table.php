<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('image_template_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // horizontal, vertical, square
            $table->integer('width');
            $table->integer('height');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('image_template_settings');
    }
};
