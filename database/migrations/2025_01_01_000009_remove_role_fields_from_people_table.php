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
            // Удаляем поля role и role_ended_at
            $table->dropColumn(['role', 'role_ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Восстанавливаем поля
            $table->string('role')->nullable();
            $table->date('role_ended_at')->nullable();
        });
    }
};
