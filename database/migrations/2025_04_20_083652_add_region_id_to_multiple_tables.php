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
        // Добавление region_id в таблицу clubs
        Schema::table('clubs', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('id');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });

        // Добавление region_id в таблицу articles
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('id');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });

        // Добавление region_id в таблицу events
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('id');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });

        // Добавление region_id в таблицу arenas
        Schema::table('arenas', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('id');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаление region_id из таблицы clubs
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });

        // Удаление region_id из таблицы articles
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });

        // Удаление region_id из таблицы events
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });

        // Удаление region_id из таблицы arenas
        Schema::table('arenas', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });
    }
};
