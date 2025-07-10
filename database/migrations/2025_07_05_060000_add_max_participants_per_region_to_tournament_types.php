<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tournament_types', function (Blueprint $table) {
            $table->unsignedInteger('max_participants_per_region')->default(0)->after('promotion_bonus');
        });
    }

    public function down()
    {
        Schema::table('tournament_types', function (Blueprint $table) {
            $table->dropColumn('max_participants_per_region');
        });
    }
};
