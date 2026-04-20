<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('competition_seasons')) {
            Schema::create('competition_seasons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->constrained('competitions')->onDelete('cascade');
                $table->foreignId('season_id')->constrained('seasons')->onDelete('cascade');
                $table->string('title')->nullable(); // Название сезона (например "2024/2025")
                $table->date('date_from')->nullable(); // Дата начала сезона
                $table->date('date_to')->nullable(); // Дата окончания сезона
                $table->boolean('is_active')->default(true); // Активный ли сезон
                $table->text('description')->nullable(); // Описание сезона
                $table->timestamps();

                $table->unique(['competition_id', 'season_id']);
                $table->index(['competition_id', 'is_active']);
                $table->index(['date_from', 'date_to']);
            });

            return;
        }

        Schema::table('competition_seasons', function (Blueprint $table) {
            if (!Schema::hasColumn('competition_seasons', 'title')) {
                $table->string('title')->nullable()->after('season_id');
            }

            if (!Schema::hasColumn('competition_seasons', 'date_from')) {
                $table->date('date_from')->nullable()->after('title');
            }

            if (!Schema::hasColumn('competition_seasons', 'date_to')) {
                $table->date('date_to')->nullable()->after('date_from');
            }

            if (!Schema::hasColumn('competition_seasons', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('date_to');
            }

            if (!Schema::hasColumn('competition_seasons', 'description')) {
                $table->text('description')->nullable()->after('is_active');
            }
        });

        Schema::table('competition_seasons', function (Blueprint $table) {
            try {
                $table->index(['competition_id', 'is_active']);
            } catch (\Throwable $e) {
            }

            try {
                $table->index(['date_from', 'date_to']);
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('competition_seasons')) {
            return;
        }

        Schema::table('competition_seasons', function (Blueprint $table) {
            $columns = [];

            foreach (['title', 'date_from', 'date_to', 'is_active', 'description'] as $column) {
                if (Schema::hasColumn('competition_seasons', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
