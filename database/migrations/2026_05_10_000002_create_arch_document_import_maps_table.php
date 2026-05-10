<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arch_document_import_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_document_id')->unique();
            $table->unsignedBigInteger('new_document_id')->unique();
            $table->string('file_name')->nullable();
            $table->string('status')->default('imported');
            $table->text('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arch_document_import_maps');
    }
};
