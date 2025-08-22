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
        Schema::create('parser_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_template_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->enum('status', ['success', 'error', 'partial']);
            $table->json('parsed_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parser_logs');
    }
};
