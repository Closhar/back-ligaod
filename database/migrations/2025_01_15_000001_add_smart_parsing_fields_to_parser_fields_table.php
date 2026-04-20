<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parser_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('parser_fields', 'search_context')) {
                $table->string('search_context')->nullable()->comment('Контекст для поиска данных');
            }

            if (!Schema::hasColumn('parser_fields', 'search_phrase')) {
                $table->string('search_phrase')->nullable()->comment('Фраза для поиска конкретных данных');
            }

            if (!Schema::hasColumn('parser_fields', 'value_separator')) {
                $table->string('value_separator')->nullable()->comment('Разделитель для значений');
            }

            if (!Schema::hasColumn('parser_fields', 'team_identification')) {
                $table->json('team_identification')->nullable()->comment('Настройки для идентификации команд');
            }

            if (!Schema::hasColumn('parser_fields', 'result_format')) {
                $table->string('result_format')->nullable()->comment('Формат вывода результата');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parser_fields', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'search_context',
                'search_phrase',
                'value_separator',
                'team_identification',
                'result_format',
            ] as $column) {
                if (Schema::hasColumn('parser_fields', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
