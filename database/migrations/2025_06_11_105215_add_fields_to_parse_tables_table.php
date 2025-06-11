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
        Schema::table('parse_tables', function (Blueprint $table) {
            $table->string('url')->nullable();
            $table->integer('table_no')->nullable();
            $table->timestamp('last_parse_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parse_tables', function (Blueprint $table) {
            $table->dropColumn(['url', 'table_no', 'last_parse_data']);
        });
    }
};
