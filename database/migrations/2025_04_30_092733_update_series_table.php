<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn('is_series');
            $table->foreignId('series_type_id')->after('description')->nullable()->constrained('series_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->string('is_series')->default('1');
            $table->dropForeign(['series_type_id']);
            $table->dropColumn('series_type_id');
        });
    }
};
