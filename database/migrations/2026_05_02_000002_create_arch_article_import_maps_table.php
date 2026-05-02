<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arch_article_import_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_article_id')->unique();
            $table->unsignedBigInteger('new_article_id')->unique();
            $table->timestamps();

            $table->foreign('new_article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arch_article_import_maps');
    }
};
