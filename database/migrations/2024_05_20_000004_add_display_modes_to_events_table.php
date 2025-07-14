<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('display_lineups_mode')->default('column')->after('id'); // column, row, comma
            $table->string('display_actions_mode')->default('single_column')->after('display_lineups_mode'); // single_column, by_team
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['display_lineups_mode', 'display_actions_mode']);
        });
    }
};
