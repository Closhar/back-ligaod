<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramClientService;

class CheckTelegramConfig extends Command
{
    protected $signature = 'telegram:check-config';
    protected $description = 'Проверка конфигурации Telegram';

    public function handle()
    {
        $this->info('Проверка конфигурации Telegram...');

        // Проверяем наличие необходимых переменных окружения
        $requiredVars = [
            'TELEGRAM_API_ID',
            'TELEGRAM_API_HASH',
            'TELEGRAM_PHONE',
        ];

        $missingVars = [];
        foreach ($requiredVars as $var) {
            if (!env($var)) {
                $missingVars[] = $var;
            }
        }

        if (!empty($missingVars)) {
            $this->error('Отсутствуют следующие переменные окружения:');
            foreach ($missingVars as $var) {
                $this->error("- {$var}");
            }
            return 1;
        }

        $this->info('Все необходимые переменные окружения присутствуют.');

        // Проверяем инициализацию MadelineProto
        try {
            $telegramService = app(TelegramClientService::class);
            $self = $telegramService->getSelf();
            $this->info('MadelineProto успешно инициализирован.');
            $this->info('Текущий пользователь: ' . ($self['username'] ?? 'Неизвестно'));
        } catch (\Exception $e) {
            $this->error('Ошибка при инициализации MadelineProto:');
            $this->error($e->getMessage());
            return 1;
        }

        $this->info('Проверка конфигурации завершена успешно.');
        return 0;
    }
}
