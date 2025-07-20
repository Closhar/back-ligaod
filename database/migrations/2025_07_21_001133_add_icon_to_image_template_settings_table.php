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
        Schema::table('image_template_settings', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('height')->comment('Icon name for nuxt-icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_template_settings', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
