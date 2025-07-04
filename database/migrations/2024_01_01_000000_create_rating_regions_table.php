<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rating_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Название региона
            $table->string('code')->unique(); // Код региона (например, 'moscow', 'spb')
            $table->text('description')->nullable(); // Описание региона
            $table->boolean('is_active')->default(true); // Активен ли регион
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rating_regions');
    }
};
