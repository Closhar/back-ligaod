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
        Schema::table('tournament_types', function (Blueprint $table) {
            $table->integer('participation_points')->default(0)->after('coefficient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_types', function (Blueprint $table) {
            $table->dropColumn('participation_points');
        });
    }
};
