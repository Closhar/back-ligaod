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

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    public function up(): void
    {
        if (
            Schema::hasTable('person_club_memberships') &&
            Schema::hasTable('clubs') &&
            ! $this->hasForeignKey('person_club_memberships', 'club_id')
        ) {
            Schema::table('person_club_memberships', function (Blueprint $table) {
                $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
            });
        }

        if (
            Schema::hasTable('person_sport_memberships') &&
            Schema::hasTable('sports') &&
            ! $this->hasForeignKey('person_sport_memberships', 'sport_id')
        ) {
            Schema::table('person_sport_memberships', function (Blueprint $table) {
                $table->foreign('sport_id')->references('id')->on('sports')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('person_sport_memberships') &&
            $this->hasForeignKey('person_sport_memberships', 'sport_id')
        ) {
            Schema::table('person_sport_memberships', function (Blueprint $table) {
                $table->dropForeign(['sport_id']);
            });
        }

        if (
            Schema::hasTable('person_club_memberships') &&
            $this->hasForeignKey('person_club_memberships', 'club_id')
        ) {
            Schema::table('person_club_memberships', function (Blueprint $table) {
                $table->dropForeign(['club_id']);
            });
        }
    }
};
