<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_lineups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('player_name')->nullable();
            $table->integer('number')->nullable();
            $table->unsignedBigInteger('parent_lineup_id')->nullable();
            $table->integer('minute_in')->nullable();
            $table->integer('minute_out')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('club_id')->references('id')->on('clubs')->onDelete('cascade');
            $table->foreign('person_id')->references('id')->on('people')->onDelete('set null');
            $table->foreign('parent_lineup_id')->references('id')->on('event_lineups')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_lineups');
    }
};
