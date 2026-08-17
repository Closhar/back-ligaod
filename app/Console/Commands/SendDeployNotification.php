<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDeployNotification extends Command
{
    protected $signature = 'deploy:notify {status} {release}';
    protected $description = 'Send deployment status notification by email';

    public function handle(): int
    {
        $recipient = config('app.deploy_notify_email');
        if (! $recipient) {
            $this->warn('DEPLOY_NOTIFY_EMAIL is not configured.');
            return self::SUCCESS;
        }

        $status = $this->argument('status');
        $release = $this->argument('release');
        Mail::raw("Деплой LIGAOD.RU завершён успешно.\nРелиз: {$release}\nВремя (UTC): ".now()->toDateTimeString(), function ($message) use ($recipient) {
            $message->to($recipient)->subject('LIGAOD.RU: деплой выполнен успешно');
        });
        return self::SUCCESS;
    }
}
