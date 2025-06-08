<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramClientService;

class TelegramInitSession extends Command
{
    protected $signature = 'telegram:init-session';
    protected $description = 'Инициализация сессии Telegram Client API';

    public function handle()
    {
        $this->info('Инициализация сессии Telegram Client API...');

        try {
            $service = new TelegramClientService();
            $this->info('Сессия успешно инициализирована!');
        } catch (\Exception $e) {
            $this->error('Ошибка при инициализации сессии: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
