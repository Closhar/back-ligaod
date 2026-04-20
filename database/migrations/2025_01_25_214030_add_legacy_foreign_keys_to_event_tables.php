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
            Schema::hasTable('event_lineups') &&
            Schema::hasTable('events') &&
            Schema::hasTable('clubs') &&
            Schema::hasTable('people')
        ) {
            Schema::table('event_lineups', function (Blueprint $table) {
                if (! $this->hasForeignKey('event_lineups', 'event_id')) {
                    $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
                }

                if (! $this->hasForeignKey('event_lineups', 'club_id')) {
                    $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
                }

                if (! $this->hasForeignKey('event_lineups', 'person_id')) {
                    $table->foreign('person_id')->references('id')->on('people')->nullOnDelete();
                }

                if (! $this->hasForeignKey('event_lineups', 'parent_lineup_id')) {
                    $table->foreign('parent_lineup_id')->references('id')->on('event_lineups')->nullOnDelete();
                }
            });
        }

        if (
            Schema::hasTable('event_actions') &&
            Schema::hasTable('events') &&
            Schema::hasTable('clubs') &&
            Schema::hasTable('people') &&
            Schema::hasTable('action_types')
        ) {
            Schema::table('event_actions', function (Blueprint $table) {
                if (! $this->hasForeignKey('event_actions', 'event_id')) {
                    $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
                }

                if (! $this->hasForeignKey('event_actions', 'club_id')) {
                    $table->foreign('club_id')->references('id')->on('clubs')->cascadeOnDelete();
                }

                if (! $this->hasForeignKey('event_actions', 'person_id')) {
                    $table->foreign('person_id')->references('id')->on('people')->nullOnDelete();
                }

                if (! $this->hasForeignKey('event_actions', 'action_type_id')) {
                    $table->foreign('action_type_id')->references('id')->on('action_types')->cascadeOnDelete();
                }

                if (! $this->hasForeignKey('event_actions', 'related_action_id')) {
                    $table->foreign('related_action_id')->references('id')->on('event_actions')->nullOnDelete();
                }
            });
        }

        if (
            Schema::hasTable('event_team_actions') &&
            Schema::hasTable('events') &&
            Schema::hasTable('team_action_types')
        ) {
            Schema::table('event_team_actions', function (Blueprint $table) {
                if (! $this->hasForeignKey('event_team_actions', 'event_id')) {
                    $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
                }

                if (! $this->hasForeignKey('event_team_actions', 'team_action_type_id')) {
                    $table->foreign('team_action_type_id')->references('id')->on('team_action_types')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_team_actions')) {
            Schema::table('event_team_actions', function (Blueprint $table) {
                if ($this->hasForeignKey('event_team_actions', 'event_id')) {
                    $table->dropForeign(['event_id']);
                }

                if ($this->hasForeignKey('event_team_actions', 'team_action_type_id')) {
                    $table->dropForeign(['team_action_type_id']);
                }
            });
        }

        if (Schema::hasTable('event_actions')) {
            Schema::table('event_actions', function (Blueprint $table) {
                if ($this->hasForeignKey('event_actions', 'event_id')) {
                    $table->dropForeign(['event_id']);
                }

                if ($this->hasForeignKey('event_actions', 'club_id')) {
                    $table->dropForeign(['club_id']);
                }

                if ($this->hasForeignKey('event_actions', 'person_id')) {
                    $table->dropForeign(['person_id']);
                }

                if ($this->hasForeignKey('event_actions', 'action_type_id')) {
                    $table->dropForeign(['action_type_id']);
                }

                if ($this->hasForeignKey('event_actions', 'related_action_id')) {
                    $table->dropForeign(['related_action_id']);
                }
            });
        }

        if (Schema::hasTable('event_lineups')) {
            Schema::table('event_lineups', function (Blueprint $table) {
                if ($this->hasForeignKey('event_lineups', 'event_id')) {
                    $table->dropForeign(['event_id']);
                }

                if ($this->hasForeignKey('event_lineups', 'club_id')) {
                    $table->dropForeign(['club_id']);
                }

                if ($this->hasForeignKey('event_lineups', 'person_id')) {
                    $table->dropForeign(['person_id']);
                }

                if ($this->hasForeignKey('event_lineups', 'parent_lineup_id')) {
                    $table->dropForeign(['parent_lineup_id']);
                }
            });
        }
    }
};
