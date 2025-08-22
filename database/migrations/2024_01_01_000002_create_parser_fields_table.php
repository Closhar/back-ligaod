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
        Schema::create('parser_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_template_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('selector');
            $table->enum('selector_type', ['css', 'xpath']);
            $table->string('data_type');
            $table->string('target_table')->nullable();
            $table->string('target_field')->nullable();
            $table->enum('update_strategy', ['insert', 'update', 'upsert']);
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parser_fields');
    }
};
