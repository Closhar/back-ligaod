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
        // Пересчитываем все достижения с учетом вида спорта при группировке
        \App\Models\ClubAchievement::recalculateRegionLimit();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // В случае отката миграции, пересчитываем по старой логике
        // (но это не рекомендуется, так как старый алгоритм был неправильным)
    }
};
