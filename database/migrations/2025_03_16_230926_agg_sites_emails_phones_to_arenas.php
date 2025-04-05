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
            $table->text('sites')->nullable()->after('about');
            $table->text('emails')->nullable()->after('sites');
            $table->text('phones')->nullable()->after('emails');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arenas', function (Blueprint $table) {
            $table->dropColumn('sites');
            $table->dropColumn('emails');
            $table->dropColumn('phones');
        });
    }
};
