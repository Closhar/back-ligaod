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
        Schema::table('streams', function (Blueprint $table) {
            $table->boolean('in_profile')->default(false)->after('in_player');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn('in_profile');
        });
    }
};
