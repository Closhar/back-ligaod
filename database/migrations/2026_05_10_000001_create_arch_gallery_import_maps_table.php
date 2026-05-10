<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arch_gallery_import_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_gallery_id')->unique();
            $table->unsignedBigInteger('new_gallery_id')->unique();
            $table->string('album')->nullable();
            $table->unsignedInteger('images_count')->default(0);
            $table->string('status')->default('imported');
            $table->text('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arch_gallery_import_maps');
    }
};
