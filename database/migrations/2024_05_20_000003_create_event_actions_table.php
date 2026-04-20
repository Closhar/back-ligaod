<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('player_name')->nullable();
            $table->unsignedBigInteger('action_type_id');
            $table->integer('minute')->nullable();
            $table->integer('value')->nullable();
            $table->unsignedBigInteger('related_action_id')->nullable();
            $table->string('extra_info')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('score')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_actions');
    }
};
