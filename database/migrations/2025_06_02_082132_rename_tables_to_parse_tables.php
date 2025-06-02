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
        Schema::rename('tables', 'parse_tables');
        Schema::rename('table_contents', 'parse_table_contents');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('parse_tables', 'tables');
        Schema::rename('parse_table_contents', 'table_contents');
    }
};
