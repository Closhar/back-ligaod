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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable()->default(null);
            $table->bigInteger('competition_id')->unsigned()->nullable()->default(null);
            $table->foreign('competition_id')->references('id')->on('competitions')->onDelete('cascade');
            $table->bigInteger('arena_id')->unsigned()->nullable()->default(null);
            $table->foreign('arena_id')->references('id')->on('arenas')->onDelete('set null');
            $table->dateTime('date_from');
            $table->dateTime('date_to')->nullable()->default(null);
            $table->bigInteger('club1_id')->unsigned()->nullable()->default(null);
            $table->foreign('club1_id')->references('id')->on('clubs')->onDelete('set null');
            $table->bigInteger('club2_id')->unsigned()->nullable()->default(null);
            $table->foreign('club2_id')->references('id')->on('clubs')->onDelete('set null');
            $table->string('result')->nullable()->default(null);
            $table->text('image')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
