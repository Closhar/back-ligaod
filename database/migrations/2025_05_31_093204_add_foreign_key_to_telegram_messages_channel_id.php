<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasForeignKey(string $table, string $column): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $foreignKeys = DB::select("PRAGMA foreign_key_list('{$table}')");

            foreach ($foreignKeys as $foreignKey) {
                if (($foreignKey->from ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    public function up(): void
    {
        if (
            !Schema::hasTable('telegram_messages') ||
            !Schema::hasTable('telegram_channels') ||
            $this->hasForeignKey('telegram_messages', 'channel_id')
        ) {
            return;
        }

        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('telegram_channels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('telegram_messages') ||
            ! $this->hasForeignKey('telegram_messages', 'channel_id')
        ) {
            return;
        }

        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
    }
};
