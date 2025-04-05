<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('arenas', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique()->nullable()->default(null);
            $table->bigInteger('city_id')->unsigned()->nullable()->default(null)->index();
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            $table->text('about')->nullable()->default(null);
            $table->text('address')->nullable()->default(null);
            $table->text('map')->nullable()->default(null);
            $table->text('image')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arenas');
    }
};
