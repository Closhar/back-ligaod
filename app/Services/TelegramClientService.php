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
        $this->logPath = public_path('MadelineProto/madeline.log');

        // Создаем директорию для сессии, если она не существует
        if (!file_exists($this->sessionPath)) {
            mkdir($this->sessionPath, 0777, true);
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
            $logger->setLevel(\danog\MadelineProto\Logger::VERBOSE);
            $logger->setExtra($this->logPath);
            $settings->setLogger($logger);

            // Настройки сериализации
            $serialization = new \danog\MadelineProto\Settings\Serialization;
            $serialization->setInterval(30);
            $serialization->setPath($this->sessionPath . '/session.madeline');
            $settings->setSerialization($serialization);

            $this->madelineProto = new \danog\MadelineProto\API($this->sessionPath . '/session.madeline', $settings);
        } catch (\Exception $e) {
            Log::error('Ошибка инициализации MadelineProto: ' . $e->getMessage());
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
            throw new \Exception('Превышен лимит запросов к Telegram API');
        }

        try {
            // Получаем peer (канал)
            $peer = $this->madelineProto->getPwrChat($channel);

            // Получаем сообщения
            $messages = $this->madelineProto->messages->getHistory([
                'peer' => $channel,
                'offset_id' => 0,
                'offset_date' => 0,
                'add_offset' => 0,
                'limit' => $limit,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);

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
}
