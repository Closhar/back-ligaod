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
        Schema::table('parser_fields', function (Blueprint $table) {
            // Контекст поиска (например, "Статистика матча:")
            $table->string('search_context')->nullable()->after('extraction_rules')->comment('Контекст для поиска данных');

            // Поисковая фраза (например, "Броски:")
            $table->string('search_phrase')->nullable()->after('search_context')->comment('Фраза для поиска конкретных данных');

            // Разделитель значений (например, "-", ":", " ")
            $table->string('value_separator')->nullable()->after('search_phrase')->comment('Разделитель для значений');

            // Идентификация команд
            $table->json('team_identification')->nullable()->after('value_separator')->comment('Настройки для идентификации команд');

            // Формат результата
            $table->string('result_format')->nullable()->after('team_identification')->comment('Формат вывода результата');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parser_fields', function (Blueprint $table) {
            $table->dropColumn([
                'search_context',
                'search_phrase',
                'value_separator',
                'team_identification',
                'result_format'
            ]);
        });
    }
};
