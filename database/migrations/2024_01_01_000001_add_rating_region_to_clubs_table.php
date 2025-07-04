<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->foreignId('rating_region_id')->nullable()->constrained('rating_regions')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['rating_region_id']);
            $table->dropColumn('rating_region_id');
        });
    }
};
