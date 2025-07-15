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

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('club_id')->references('id')->on('clubs')->onDelete('cascade');
            $table->foreign('person_id')->references('id')->on('people')->onDelete('set null');
            $table->foreign('action_type_id')->references('id')->on('action_types')->onDelete('cascade');
            $table->foreign('related_action_id')->references('id')->on('event_actions')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_actions');
    }
};
