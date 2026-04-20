<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasForeignKey(string $table, string $column): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $foreignKeys = DB::select("PRAGMA foreign_key_list('{$table}')");

            foreach ($foreignKeys as $foreignKey) {
                if (($foreignKey->from ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    public function up(): void
    {
        if (
            Schema::hasTable('clubs') &&
            Schema::hasTable('rating_regions') &&
            !Schema::hasColumn('clubs', 'rating_region_id')
        ) {
            Schema::table('clubs', function (Blueprint $table) {
                $table->foreignId('rating_region_id')->nullable()->after('image')->constrained('rating_regions')->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('region_ratings') &&
            Schema::hasTable('sports') &&
            !$this->hasForeignKey('region_ratings', 'sport_id')
        ) {
            Schema::table('region_ratings', function (Blueprint $table) {
                $table->foreign('sport_id')->references('id')->on('sports')->cascadeOnDelete();
            });
        }

        if (
            Schema::hasTable('club_achievements') &&
            Schema::hasTable('clubs') &&
            !$this->hasForeignKey('club_achievements', 'club_id')
        ) {
            Schema::table('club_achievements', function (Blueprint $table) {
                $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('club_achievements') &&
            Schema::hasColumn('club_achievements', 'club_id') &&
            $this->hasForeignKey('club_achievements', 'club_id')
        ) {
            Schema::table('club_achievements', function (Blueprint $table) {
                $table->dropForeign(['club_id']);
            });
        }

        if (
            Schema::hasTable('region_ratings') &&
            Schema::hasColumn('region_ratings', 'sport_id') &&
            $this->hasForeignKey('region_ratings', 'sport_id')
        ) {
            Schema::table('region_ratings', function (Blueprint $table) {
                $table->dropForeign(['sport_id']);
            });
        }

        if (
            Schema::hasTable('clubs') &&
            Schema::hasColumn('clubs', 'rating_region_id')
        ) {
            Schema::table('clubs', function (Blueprint $table) {
                $table->dropForeign(['rating_region_id']);
                $table->dropColumn('rating_region_id');
            });
        }
    }
};
