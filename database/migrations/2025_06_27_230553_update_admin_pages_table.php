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
        Schema::table('admin_pages', function (Blueprint $table) {
            $table->foreignId('menu_section_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('sort_order')->default(0);

            $table->index('menu_section_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_pages', function (Blueprint $table) {
            $table->dropForeign(['menu_section_id']);
            $table->dropColumn(['menu_section_id', 'sort_order']);
        });
    }
};