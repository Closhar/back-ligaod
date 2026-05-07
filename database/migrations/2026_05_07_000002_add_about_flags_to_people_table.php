<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('is_president')->default(false)->after('is_management')->index();
            $table->boolean('is_vice')->default(false)->after('is_president')->index();
            $table->boolean('is_popech')->default(false)->after('is_vice')->index();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['is_president', 'is_vice', 'is_popech']);
        });
    }
};
