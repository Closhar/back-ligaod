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
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_lineups');
    }
};
