<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'display_lineups_mode')) {
                $table->string('display_lineups_mode')->default('column')->after('id');
            }

            if (!Schema::hasColumn('events', 'display_actions_mode')) {
                $table->string('display_actions_mode')->default('single_column')->after('display_lineups_mode');
            }

            if (!Schema::hasColumn('events', 'show_numbers_club1')) {
                $table->boolean('show_numbers_club1')->default(true)->after('display_actions_mode');
            }

            if (!Schema::hasColumn('events', 'show_numbers_club2')) {
                $table->boolean('show_numbers_club2')->default(true)->after('show_numbers_club1');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'display_lineups_mode',
                'display_actions_mode',
                'show_numbers_club1',
                'show_numbers_club2',
            ] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
