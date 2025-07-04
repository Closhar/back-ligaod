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
            $table->decimal('coefficient', 3, 2)->default(1.00)->after('ignore_teams_multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_types', function (Blueprint $table) {
            $table->dropColumn('coefficient');
        });
    }
};
