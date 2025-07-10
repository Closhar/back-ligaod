<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('region_year_total_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_region_id')->constrained('rating_regions')->onDelete('cascade');
            $table->foreignId('rating_year_id')->constrained('rating_years')->onDelete('cascade');
            $table->decimal('rating', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['rating_region_id', 'rating_year_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('region_year_total_ratings');
    }
};
