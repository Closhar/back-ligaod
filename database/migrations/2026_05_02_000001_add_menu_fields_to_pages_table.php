<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (!Schema::hasColumn('pages', 'in_menu')) {
                $table->boolean('in_menu')->default(false)->after('icon')->index();
            }

            if (!Schema::hasColumn('pages', 'menu_sort')) {
                $table->unsignedInteger('menu_sort')->default(500)->after('in_menu')->index();
            }

            if (!Schema::hasColumn('pages', 'in_mobile_menu')) {
                $table->boolean('in_mobile_menu')->default(false)->after('menu_sort')->index();
            }

            if (!Schema::hasColumn('pages', 'mobile_menu_sort')) {
                $table->unsignedInteger('mobile_menu_sort')->default(500)->after('in_mobile_menu')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            foreach (['mobile_menu_sort', 'in_mobile_menu', 'menu_sort', 'in_menu'] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
