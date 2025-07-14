<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('show_numbers_club1')->default(true)->after('display_actions_mode');
            $table->boolean('show_numbers_club2')->default(true)->after('show_numbers_club1');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['show_numbers_club1', 'show_numbers_club2']);
        });
    }
};
