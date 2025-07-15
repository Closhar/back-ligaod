<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('event_lineups', function (Blueprint $table) {
            $table->boolean('is_captain')->default(false)->after('sort_order');
        });
    }

    public function down()
    {
        Schema::table('event_lineups', function (Blueprint $table) {
            $table->dropColumn('is_captain');
        });
    }
};
