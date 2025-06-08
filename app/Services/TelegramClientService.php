<?php

namespace App\Services;

use danog\MadelineProto\API;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramClientService
{
    private $madelineProto;
    private $sessionFile;
    private $cachePrefix = 'telegram_client_';
    private $rateLimit = 30;
    private $rateLimitWindow = 60;

    public function __construct()
    {
        $this->sessionFile = storage_path('app/telegram/session.madeline');
        $this->initializeMadelineProto();
    }

    /**
     * Инициализация MadelineProto
     */
    private function initializeMadelineProto()
    {
        try {
            $settings = [
                'app_info' => [
                    'api_id' => config('services.telegram.api_id'),
                    'api_hash' => config('services.telegram.api_hash')
                ],
                'connection' => [
                    'proxy' => [
                        'proxy' => config('services.telegram.proxy.enabled', false) ? 'socks5' : 'none',
                        'proxy_extra' => [
                            'address' => config('services.telegram.proxy.address'),
                            'port' => config('services.telegram.proxy.port'),
                            'username' => config('services.telegram.proxy.username'),
                            'password' => config('services.telegram.proxy.password')
                        ]
                    ]
                ],
                'serialization' => [
                    'serialization_interval' => 30,
                    'serialization_timeout' => 30
                ],
                'connection_settings' => [
                    'all' => [
                        'proxy_extra' => [
                            'proxy' => config('services.telegram.proxy.enabled', false) ? 'socks5' : 'none',
                            'proxy_extra' => [
                                'address' => config('services.telegram.proxy.address'),
                                'port' => config('services.telegram.proxy.port'),
                                'username' => config('services.telegram.proxy.username'),
                                'password' => config('services.telegram.proxy.password')
                            ]
                        ]
                    ]
                ]
            ];

            $this->madelineProto = new API($this->sessionFile, $settings);
            $this->madelineProto->start();
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
