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
        Schema::table('person_images', function (Blueprint $table) {
            $table->string('title')->nullable()->after('image_path');
            $table->integer('position')->default(0)->after('title');
            $table->boolean('is_main')->default(false)->after('position');

            // Индексы
            $table->index('position');
            $table->index('is_main');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_images', function (Blueprint $table) {
            $table->dropIndex(['position']);
            $table->dropIndex(['is_main']);
            $table->dropColumn(['title', 'position', 'is_main']);
        });
    }
};
