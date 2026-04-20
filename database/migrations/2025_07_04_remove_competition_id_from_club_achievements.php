<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('club_achievements') ||
            !Schema::hasColumn('club_achievements', 'competition_id')
        ) {
            return;
        }

        Schema::table('club_achievements', function (Blueprint $table) {
            $table->dropForeign(['competition_id']);
            $table->dropColumn('competition_id');
        });
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('club_achievements') ||
            Schema::hasColumn('club_achievements', 'competition_id')
        ) {
            return;
        }

        Schema::table('club_achievements', function (Blueprint $table) {
            $table->foreignId('competition_id')->nullable()->constrained('competitions')->onDelete('cascade');
        });
    }
};
