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
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique()->nullable()->default(null);
            $table->string('title_short')->nullable()->default(null);
            $table->bigInteger('sport_id')->unsigned()->nullable()->default(1);
            $table->foreign('sport_id')->references('id')->on('sports');
            $table->date('date_from')->nullable()->default(null);
            $table->date('date_to')->nullable()->default(null);
            $table->text('about')->nullable()->default(null);
            $table->text('image')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
