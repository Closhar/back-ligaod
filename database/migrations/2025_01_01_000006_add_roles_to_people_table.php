<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Удаляем поле is_sportsman
            $table->dropColumn('is_sportsman');

            // Добавляем новые поля
            $table->string('role')->nullable(); // амплуа для спортсменов или должность для не спортсменов
            $table->date('role_ended_at')->nullable(); // дата окончания действия амплуа/должности
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Восстанавливаем поле is_sportsman
            $table->boolean('is_sportsman')->default(true);

            // Удаляем новые поля
            $table->dropColumn(['role', 'role_ended_at']);
        });
    }
};
