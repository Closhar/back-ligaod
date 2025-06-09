<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupMadelineLogs extends Command
{
    protected $signature = 'madeline:cleanup-logs {--days=7 : Количество дней для хранения логов}';
    protected $description = 'Очистка старых лог-файлов MadelineProto';

    public function handle()
    {
        $logPath = storage_path('logs');
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $files = File::glob($logPath . '/madeline-*.log');

        foreach ($files as $file) {
            $fileDate = \DateTime::createFromFormat('Y-m-d', $this->getDateFromFilename($file));

            if ($fileDate && $fileDate < $cutoffDate) {
                File::delete($file);
                $this->info("Удален файл: " . basename($file));
            }
        }

        $this->info('Очистка логов завершена');
    }

    private function getDateFromFilename($filename)
    {
        if (preg_match('/madeline-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
