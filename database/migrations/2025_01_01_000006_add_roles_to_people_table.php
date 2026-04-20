<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['is_sportsman']);
            $table->dropColumn('is_sportsman');
            $table->string('role')->nullable(); // амплуа для спортсменов или должность для не спортсменов
            $table->date('role_ended_at')->nullable(); // дата окончания действия амплуа/должности
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('is_sportsman')->default(true);
            $table->dropColumn(['role', 'role_ended_at']);
            $table->index('is_sportsman');
        });
    }
};
