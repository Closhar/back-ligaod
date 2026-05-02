<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            if (!Schema::hasColumn('people', 'is_management')) {
                $table->boolean('is_management')->default(false)->after('is_active')->index();
            }

            if (!Schema::hasColumn('people', 'management_sort')) {
                $table->unsignedInteger('management_sort')->default(500)->after('is_management')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            if (Schema::hasColumn('people', 'management_sort')) {
                $table->dropColumn('management_sort');
            }

            if (Schema::hasColumn('people', 'is_management')) {
                $table->dropColumn('is_management');
            }
        });
    }
};
