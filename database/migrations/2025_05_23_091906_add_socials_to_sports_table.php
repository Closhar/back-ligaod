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
        Schema::table('sports', function (Blueprint $table) {
            $table->string('sites')->nullable()->after('slug');
            $table->string('vks')->nullable()->after('sites');
            $table->string('youtubes')->nullable()->after('vks');
            $table->string('telegrams')->nullable()->after('youtubes');
            $table->string('instagrams')->nullable()->after('telegrams');
            $table->string('facebooks')->nullable()->after('instagrams');
            $table->string('xs')->nullable()->after('facebooks');
            $table->foreignId('gallery_id')->nullable()->constrained('galleries')->onDelete('set null')->after('xs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sports', function (Blueprint $table) {
            $table->dropForeign(['gallery_id']);
            $table->dropColumn('gallery_id');
            $table->dropColumn('xs');
            $table->dropColumn('facebooks');
            $table->dropColumn('instagrams');
            $table->dropColumn('telegrams');
            $table->dropColumn('youtubes');
        });
    }
};
