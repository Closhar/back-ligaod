<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arch_video_import_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_video_id')->unique();
            $table->unsignedBigInteger('new_video_id')->unique();
            $table->timestamps();

            $table->foreign('new_video_id')
                ->references('id')
                ->on('videos')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arch_video_import_maps');
    }
};
