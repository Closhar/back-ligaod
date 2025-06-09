<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramClientService;
use Illuminate\Support\Facades\File;

class RecreateMadelineSession extends Command
{
    protected $signature = 'telegram:recreate-session';
    protected $description = 'Пересоздание сессии MadelineProto';

    public function handle()
    {
        $this->info('Начало пересоздания сессии MadelineProto...');

        try {
            // Получаем путь к файлу сессии
            $sessionPath = storage_path('app/madeline/session.madeline');

            // Удаляем старый файл сессии, если он существует
            if (File::exists($sessionPath)) {
                $this->info('Удаление старого файла сессии...');
                File::delete($sessionPath);
                $this->info('Старый файл сессии удален');
            }

            // Создаем новый экземпляр TelegramClientService
            $telegramService = app(TelegramClientService::class);

            // Проверяем авторизацию
            $this->info('Проверка авторизации...');
            $self = $telegramService->getSelf();
            $this->info('Авторизация успешна');
            $this->info('Текущий пользователь: ' . ($self['username'] ?? 'Неизвестно'));

            $this->info('Сессия MadelineProto успешно пересоздана');
            return 0;

        } catch (\Exception $e) {
            $this->error('Ошибка при пересоздании сессии:');
            $this->error($e->getMessage());
            return 1;
        }
    }
}
