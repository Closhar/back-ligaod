<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_team_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('team_action_type_id')->constrained('team_action_types')->onDelete('cascade');
            $table->integer('value_home')->default(0);
            $table->integer('value_away')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_team_actions');
    }
};
