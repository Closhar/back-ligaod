<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Connection;
use danog\MadelineProto\Settings\Proxy;
use danog\MadelineProto\Settings\Serialization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramClientService
{
    private $madelineProto;
    private $sessionPath;
    private $logPath;
    private $cachePrefix = 'telegram_parse_';
    private $rateLimit = 30;
    private $rateLimitWindow = 60;

    public function __construct()
    {
        $this->cachePrefix = 'telegram_parse_';
        $this->sessionPath = storage_path('app/madeline');
        $this->logPath = public_path('MadelineProto.log');

        // Создаем директорию для сессии, если она не существует
        if (!file_exists($this->sessionPath)) {
            mkdir($this->sessionPath, 0777, true);
        }

        // Создаем файл логов, если он не существует
        if (!file_exists($this->logPath)) {
            touch($this->logPath);
            chmod($this->logPath, 0666);
        }

        try {
            $settings = new \danog\MadelineProto\Settings;

            // Настройки приложения
            $appInfo = new \danog\MadelineProto\Settings\AppInfo;
            $appInfo->setApiId((int)config('services.telegram.api_id'));
            $appInfo->setApiHash(config('services.telegram.api_hash'));
            $settings->setAppInfo($appInfo);

            // Настройки логгера
            $logger = new \danog\MadelineProto\Settings\Logger;
            $logger->setType(\danog\MadelineProto\Logger::FILE_LOGGER);
            $logger->setLevel(\danog\MadelineProto\Logger::NOTICE);
            $logger->setExtra($this->logPath);
            $settings->setLogger($logger);

            // Настройки сериализации
            $serialization = new \danog\MadelineProto\Settings\Serialization;
            $serialization->setInterval(30);
            $settings->setSerialization($serialization);

            $this->madelineProto = new \danog\MadelineProto\API($this->sessionPath . '/session.madeline', $settings);

            // Выполняем авторизацию
            $this->login();
        } catch (\Exception $e) {
            Log::error('Ошибка инициализации MadelineProto: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Авторизация в Telegram
     */
    private function login()
    {
        try {
            if (!$this->madelineProto->getSelf()) {
                Log::info('Начинаем процесс авторизации в Telegram');

                // Получаем номер телефона из конфигурации
                $phone = config('services.telegram.phone');
                if (empty($phone)) {
                    throw new \Exception('Номер телефона не указан в конфигурации');
                }

                // Отправляем код подтверждения
                $sentCode = $this->madelineProto->phoneLogin($phone);

                // Получаем код из конфигурации
                $code = config('services.telegram.code');
                if (empty($code)) {
                    throw new \Exception('Код подтверждения не указан в конфигурации');
                }

                // Подтверждаем код
                $authorization = $this->madelineProto->completePhoneLogin($code);

                if ($authorization['_'] === 'account.password') {
                    // Если требуется двухфакторная аутентификация
                    $password = config('services.telegram.password');
                    if (empty($password)) {
                        throw new \Exception('Пароль двухфакторной аутентификации не указан в конфигурации');
                    }
                    $this->madelineProto->complete2faLogin($password);
                }

                Log::info('Авторизация в Telegram успешно завершена');
            }
        } catch (\Exception $e) {
            Log::error('Ошибка авторизации в Telegram: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получение сообщений из публичного канала
     */
    public function getChannelMessages($channel, $dateFrom, $limit = 100)
    {
        $cacheKey = $this->cachePrefix . "messages_{$channel}_{$dateFrom}";

        // Проверяем кэш
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Проверяем ограничение запросов
        if (!$this->checkRateLimit()) {
            Log::error('Превышен лимит запросов к Telegram API');
            throw new \Exception('Превышен лимит запросов к Telegram API');
        }

        try {
            // Форматируем идентификатор канала
            $channelId = $channel;
            if (!str_starts_with($channel, '@')) {
                $channelId = '@' . $channel;
            }

            Log::info('Попытка получения сообщений из канала: ' . $channelId);

            // Пробуем получить информацию о канале
            try {
                $channelInfo = $this->madelineProto->channels->getFullChannel([
                    'channel' => $channelId
                ]);
                Log::info('Получена информация о канале: ' . json_encode($channelInfo));
            } catch (\Exception $e) {
                Log::error('Ошибка получения информации о канале: ' . $e->getMessage());
                // Пробуем альтернативный формат
                $channelId = 'https://t.me/' . ltrim($channelId, '@');
                Log::info('Пробуем альтернативный формат: ' . $channelId);
            }

            // Получаем сообщения
            $messages = $this->madelineProto->messages->getHistory([
                'peer' => $channelId,
                'offset_id' => 0,
                'offset_date' => 0,
                'add_offset' => 0,
                'limit' => $limit,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);

            Log::info('Получено сообщений: ' . count($messages['messages']));

            $result = [];
            foreach ($messages['messages'] as $message) {
                if (isset($message['date']) && date('Y-m-d H:i:s', $message['date']) >= $dateFrom) {
                    $result[] = $this->formatMessage($message);
                }
            }

            // Сохраняем в кэш на 1 час
            Cache::put($cacheKey, $result, 3600);

            return $result;
        } catch (\Exception $e) {
            Log::error('Ошибка получения сообщений через MadelineProto: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Проверка ограничения запросов
     */
    private function checkRateLimit()
    {
        $key = 'telegram_client_rate_limit';
        $requests = Cache::get($key, []);

        $now = time();
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return $timestamp > ($now - $this->rateLimitWindow);
        });

        if (count($requests) >= $this->rateLimit) {
            return false;
        }

        $requests[] = $now;
        Cache::put($key, $requests, $this->rateLimitWindow);

        return true;
    }

    /**
     * Форматирование сообщения
     */
    private function formatMessage($message)
    {
        $formatted = [
            'message_id' => $message['id'],
            'date' => date('Y-m-d H:i:s', $message['date']),
            'text' => $message['message'] ?? null,
            'media' => null,
            'raw_data' => $message
        ];

        // Обработка медиафайлов
        if (isset($message['media'])) {
            $formatted['media'] = $this->processMedia($message['media']);
        }

        return $formatted;
    }

    /**
     * Обработка медиафайлов
     */
    private function processMedia($media)
    {
        if (isset($media['photo'])) {
            return [
                'type' => 'photo',
                'file_id' => $media['photo']['id']
            ];
        } elseif (isset($media['document'])) {
            return [
                'type' => 'document',
                'file_id' => $media['document']['id'],
                'mime_type' => $media['document']['mime_type'] ?? null,
                'file_name' => $media['document']['attributes'][0]['file_name'] ?? null
            ];
        } elseif (isset($media['webpage'])) {
            return [
                'type' => 'webpage',
                'url' => $media['webpage']['url'] ?? null,
                'title' => $media['webpage']['title'] ?? null,
                'description' => $media['webpage']['description'] ?? null
            ];
        }

        return null;
    }

    /**
     * Получение информации о канале
     */
    public function getChannelInfo($channel)
    {
        try {
            // Форматируем идентификатор канала
            $channelId = $channel;
            if (!str_starts_with($channel, '-100') && !str_starts_with($channel, '@')) {
                $channelId = '@' . $channel;
            }

            Log::info('Попытка получения информации о канале: ' . $channelId);

            // Пробуем получить информацию через channels.getFullChannel
            try {
                $fullChannel = $this->madelineProto->channels->getFullChannel([
                    'channel' => $channelId
                ]);
                Log::info('Получена информация через getFullChannel: ' . json_encode($fullChannel));
                return [
                    'type' => 'getFullChannel',
                    'data' => $fullChannel
                ];
            } catch (\Exception $e) {
                Log::info('Ошибка getFullChannel: ' . $e->getMessage());
            }

            // Пробуем получить информацию через channels.getChannels
            try {
                $channels = $this->madelineProto->channels->getChannels([
                    'id' => [$channelId]
                ]);
                Log::info('Получена информация через getChannels: ' . json_encode($channels));
                return [
                    'type' => 'getChannels',
                    'data' => $channels
                ];
            } catch (\Exception $e) {
                Log::info('Ошибка getChannels: ' . $e->getMessage());
            }

            // Пробуем получить информацию через channels.getMessages
            try {
                $messages = $this->madelineProto->channels->getMessages([
                    'channel' => $channelId,
                    'id' => [1] // Пробуем получить первое сообщение
                ]);
                Log::info('Получена информация через getMessages: ' . json_encode($messages));
                return [
                    'type' => 'getMessages',
                    'data' => $messages
                ];
            } catch (\Exception $e) {
                Log::info('Ошибка getMessages: ' . $e->getMessage());
            }

            // Пробуем получить информацию через channels.getParticipants
            try {
                $participants = $this->madelineProto->channels->getParticipants([
                    'channel' => $channelId,
                    'filter' => ['_' => 'channelParticipantsRecent'],
                    'offset' => 0,
                    'limit' => 1
                ]);
                Log::info('Получена информация через getParticipants: ' . json_encode($participants));
                return [
                    'type' => 'getParticipants',
                    'data' => $participants
                ];
            } catch (\Exception $e) {
                Log::info('Ошибка getParticipants: ' . $e->getMessage());
            }

            // Пробуем получить информацию о себе (для проверки авторизации)
            try {
                $self = $this->madelineProto->getSelf();
                Log::info('Информация о текущем пользователе: ' . json_encode($self));
                return [
                    'type' => 'self',
                    'data' => $self,
                    'error' => 'Не удалось получить информацию о канале, но авторизация работает'
                ];
            } catch (\Exception $e) {
                Log::info('Ошибка получения информации о себе: ' . $e->getMessage());
                throw new \Exception('Проблема с авторизацией: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::error('Ошибка получения информации о канале: ' . $e->getMessage());
            throw $e;
        }
    }
}
