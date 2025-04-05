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
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_short');
            $table->string('slug')->unique()->nullable()->default(null);
            $table->text('about')->nullable();
            $table->text('address')->nullable();
            $table->text('map')->nullable();
            $table->BigInteger('sport_id')->unsigned()->nullable()->default(null);
            $table->foreign('sport_id')->references('id')->on('sports')->onDelete('set null');
            $table->BigInteger('city_id')->unsigned()->nullable()->default(null);
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            $table->BigInteger('gender_id')->unsigned()->nullable()->default(null);
            $table->foreign('gender_id')->references('id')->on('genders')->onDelete('set null');
            $table->BigInteger('age_id')->unsigned()->nullable()->default(null);
            $table->foreign('age_id')->references('id')->on('ages')->onDelete('set null');
            $table->boolean('is_alien')->default(1);
            $table->string('image')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
