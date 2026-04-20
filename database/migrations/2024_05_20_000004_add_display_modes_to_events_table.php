<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (
            !Schema::hasTable('events') ||
            Schema::hasColumn('events', 'display_lineups_mode') ||
            Schema::hasColumn('events', 'display_actions_mode')
        ) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->string('display_lineups_mode')->default('column')->after('id'); // column, row, comma
            $table->string('display_actions_mode')->default('single_column')->after('display_lineups_mode'); // single_column, by_team
        });
    }

    public function down()
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('events', 'display_lineups_mode')) {
                $columns[] = 'display_lineups_mode';
            }

            if (Schema::hasColumn('events', 'display_actions_mode')) {
                $columns[] = 'display_actions_mode';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
