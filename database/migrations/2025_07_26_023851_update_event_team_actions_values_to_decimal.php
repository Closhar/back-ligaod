<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_team_actions', function (Blueprint $table) {
            $table->decimal('value_home', 8, 2)->default(0)->change();
            $table->decimal('value_away', 8, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_team_actions', function (Blueprint $table) {
            $table->integer('value_home')->default(0)->change();
            $table->integer('value_away')->default(0)->change();
        });
    }
};
