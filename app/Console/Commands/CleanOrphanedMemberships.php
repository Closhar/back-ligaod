<?php

namespace App\Console\Commands;

use App\Models\PersonAmpluaMembership;
use App\Models\PersonClubMembership;
use App\Models\PersonPositionMembership;
use App\Models\PersonSportMembership;
use Illuminate\Console\Command;

class CleanOrphanedMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:clean-orphaned {--dry-run : Показать записи без удаления}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистить "сиротские" записи в таблицах членств';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 РЕЖИМ ПРЕДВАРИТЕЛЬНОГО ПРОСМОТРА - записи не будут удалены');
        } else {
            $this->warn('⚠️  ВНИМАНИЕ: Будут удалены "сиротские" записи!');
            if (!$this->confirm('Продолжить?')) {
                $this->info('Операция отменена.');
                return;
            }
        }

        $this->newLine();

        // 1. Очистка членств в клубах
        $this->cleanClubMemberships($isDryRun);

        // 2. Очистка членств в должностях
        $this->cleanPositionMemberships($isDryRun);

        // 3. Очистка членств в амплуа
        $this->cleanAmpluaMemberships($isDryRun);

        // 4. Очистка членств в видах спорта
        $this->cleanSportMemberships($isDryRun);

        $this->newLine();
        $this->info('✅ Очистка завершена!');
    }

    private function cleanClubMemberships(bool $isDryRun): void
    {
        $this->info('🔍 Проверка членств в клубах...');

        $orphanedMemberships = PersonClubMembership::whereDoesntHave('club')->get();

        if ($orphanedMemberships->isEmpty()) {
            $this->info('   ✅ Проблемных записей не найдено');
            return;
        }

        $this->warn("   ⚠️  Найдено {$orphanedMemberships->count()} проблемных записей:");

        foreach ($orphanedMemberships as $membership) {
            $personName = $membership->person ? $membership->person->full_name : "ID: {$membership->person_id}";
            $this->line("      - ID: {$membership->id}, Персона: {$personName}, Клуб ID: {$membership->club_id}");
        }

        if (!$isDryRun) {
            $deleted = PersonClubMembership::whereDoesntHave('club')->delete();
            $this->info("   ✅ Удалено {$deleted} записей");
        }
    }

    private function cleanPositionMemberships(bool $isDryRun): void
    {
        $this->info('🔍 Проверка членств в должностях...');

        $orphanedMemberships = PersonPositionMembership::whereDoesntHave('position')->get();

        if ($orphanedMemberships->isEmpty()) {
            $this->info('   ✅ Проблемных записей не найдено');
            return;
        }

        $this->warn("   ⚠️  Найдено {$orphanedMemberships->count()} проблемных записей:");

        foreach ($orphanedMemberships as $membership) {
            $personName = $membership->person ? $membership->person->full_name : "ID: {$membership->person_id}";
            $this->line("      - ID: {$membership->id}, Персона: {$personName}, Должность ID: {$membership->position_id}");
        }

        if (!$isDryRun) {
            $deleted = PersonPositionMembership::whereDoesntHave('position')->delete();
            $this->info("   ✅ Удалено {$deleted} записей");
        }
    }

    private function cleanAmpluaMemberships(bool $isDryRun): void
    {
        $this->info('🔍 Проверка членств в амплуа...');

        $orphanedMemberships = PersonAmpluaMembership::whereDoesntHave('amplua')->get();

        if ($orphanedMemberships->isEmpty()) {
            $this->info('   ✅ Проблемных записей не найдено');
            return;
        }

        $this->warn("   ⚠️  Найдено {$orphanedMemberships->count()} проблемных записей:");

        foreach ($orphanedMemberships as $membership) {
            $personName = $membership->person ? $membership->person->full_name : "ID: {$membership->person_id}";
            $this->line("      - ID: {$membership->id}, Персона: {$personName}, Амплуа ID: {$membership->amplua_id}");
        }

        if (!$isDryRun) {
            $deleted = PersonAmpluaMembership::whereDoesntHave('amplua')->delete();
            $this->info("   ✅ Удалено {$deleted} записей");
        }
    }

    private function cleanSportMemberships(bool $isDryRun): void
    {
        $this->info('🔍 Проверка членств в видах спорта...');

        $orphanedMemberships = PersonSportMembership::whereDoesntHave('sport')->get();

        if ($orphanedMemberships->isEmpty()) {
            $this->info('   ✅ Проблемных записей не найдено');
            return;
        }

        $this->warn("   ⚠️  Найдено {$orphanedMemberships->count()} проблемных записей:");

        foreach ($orphanedMemberships as $membership) {
            $personName = $membership->person ? $membership->person->full_name : "ID: {$membership->person_id}";
            $this->line("      - ID: {$membership->id}, Персона: {$personName}, Вид спорта ID: {$membership->sport_id}");
        }

        if (!$isDryRun) {
            $deleted = PersonSportMembership::whereDoesntHave('sport')->delete();
            $this->info("   ✅ Удалено {$deleted} записей");
        }
    }
}
