<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('region_year_total_ratings', function (Blueprint $table) {
            $table->decimal('yearly_rating', 12, 2)->default(0)->after('rating');
        });
    }

    public function down()
    {
        Schema::table('region_year_total_ratings', function (Blueprint $table) {
            $table->dropColumn('yearly_rating');
        });
    }
};
