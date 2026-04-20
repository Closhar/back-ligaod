<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (
            !Schema::hasTable('events') ||
            Schema::hasColumn('events', 'show_numbers_club1') ||
            Schema::hasColumn('events', 'show_numbers_club2')
        ) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('show_numbers_club1')->default(true)->after('display_actions_mode');
            $table->boolean('show_numbers_club2')->default(true)->after('show_numbers_club1');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('events', 'show_numbers_club1')) {
                $columns[] = 'show_numbers_club1';
            }

            if (Schema::hasColumn('events', 'show_numbers_club2')) {
                $columns[] = 'show_numbers_club2';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
