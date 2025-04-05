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
        Schema::table('arenas', function (Blueprint $table) {
            $table->string('telegrams')->nullable()->after('phones');
            $table->string('instagrams')->nullable()->after('telegrams');
            $table->string('facebooks')->nullable()->after('instagrams');
            $table->string('xs')->nullable()->after('facebooks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arenas', function (Blueprint $table) {
            $table->dropColumn('telegrams');
            $table->dropColumn('instagrams');
            $table->dropColumn('facebooks');
            $table->dropColumn('xs');
        });
    }
};
