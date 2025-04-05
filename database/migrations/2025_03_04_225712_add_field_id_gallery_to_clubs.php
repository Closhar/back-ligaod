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
        Schema::table('clubs', function (Blueprint $table) {
            $table->unsignedBigInteger("gallery_id")->nullable()->after('map');
            $table->foreign("gallery_id")->references("id")->on("galleries")->onDelete("set null");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign("clubs_gallery_id_foreign");
            $table->dropColumn("gallery_id");
        });
    }
};
