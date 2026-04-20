<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('competition_seasons') ||
            Schema::hasColumn('competition_seasons', 'season_id')
        ) {
            return;
        }

        Schema::table('competition_seasons', function (Blueprint $table) {
            $table->bigInteger('season_id')->unsigned()->nullable()->after('competition_id');
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('competition_seasons') ||
            !Schema::hasColumn('competition_seasons', 'season_id')
        ) {
            return;
        }

        Schema::table('competition_seasons', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropColumn('season_id');
        });
    }
};
