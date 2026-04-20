<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('action_types') ||
            !Schema::hasColumn('action_types', 'short_name') ||
            Schema::hasColumn('action_types', 'short_name_table')
        ) {
            return;
        }

        Schema::table('action_types', function (Blueprint $table) {
            $table->string('short_name_table')->nullable()->after('short_name');
        });
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('action_types') ||
            !Schema::hasColumn('action_types', 'short_name_table')
        ) {
            return;
        }

        Schema::table('action_types', function (Blueprint $table) {
            $table->dropColumn('short_name_table');
        });
    }
};
