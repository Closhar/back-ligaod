<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournament_types', function (Blueprint $table) {
            $table->integer('promotion_bonus')->default(30)->after('participation_points');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_types', function (Blueprint $table) {
            $table->dropColumn('promotion_bonus');
        });
    }
};
