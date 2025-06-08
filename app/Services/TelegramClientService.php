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
use App\Models\TelegramParseChannel;

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
        try {
            // Определяем путь к сессии
            $this->sessionPath = storage_path('madeline');
            $sessionFile = $this->sessionPath . '/madeline.madeline';

            // Проверяем и создаем директорию если нужно
            if (!is_dir($this->sessionPath)) {
                if (!mkdir($this->sessionPath, 0777, true)) {
                    throw new \Exception("Не удалось создать директорию: {$this->sessionPath}");
                }
            }

            // Проверяем права на запись
            if (!is_writable($this->sessionPath)) {
                throw new \Exception("Нет прав на запись в директорию: {$this->sessionPath}");
            }

            // Создаем объект настроек
            $settings = new \danog\MadelineProto\Settings;

            // Настройки приложения
            $appInfo = new \danog\MadelineProto\Settings\AppInfo;
            $appInfo->setApiId((int)config('services.telegram.api_id'));
            $appInfo->setApiHash(config('services.telegram.api_hash'));
            $settings->setAppInfo($appInfo);

            // Отключаем логирование
            $logger = new \danog\MadelineProto\Settings\Logger;
            $logger->setType(\danog\MadelineProto\Logger::LOGGER_NONE);
            $settings->setLogger($logger);

            // Настройки сериализации
            $serialization = new \danog\MadelineProto\Settings\Serialization;
            $serialization->setInterval(30);
            $settings->setSerialization($serialization);

            // Проверяем права на файл сессии
            if (file_exists($sessionFile) && !is_writable($sessionFile)) {
                if (!chmod($sessionFile, 0666)) {
                    throw new \Exception("Не удалось установить права на файл сессии: {$sessionFile}");
                }
            }

            // Инициализация MadelineProto
            $this->madelineProto = new \danog\MadelineProto\API($sessionFile, $settings);

            \Log::info('MadelineProto успешно инициализирован', [
                'session_path' => $this->sessionPath,
                'session_file' => $sessionFile
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка инициализации MadelineProto: ' . $e->getMessage(), [
                'session_path' => $this->sessionPath ?? 'не определен'
            ]);
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
     * Получить сообщения из канала
     *
     * @param int $channelId ID записи из таблицы telegram_parse_channels
     * @param string|null $dateFrom Дата в формате Y-m-d, от которой искать сообщения
     * @param int $limit Лимит сообщений (максимум 100)
     * @return array
     * @throws \Exception
     */
    public function getChannelMessages($channelId, $dateFrom = null, $limit = 100)
    {
        try {
            // Проверяем и ограничиваем лимит
            $limit = min($limit, 100);

            // Получаем канал из базы данных
            $channel = TelegramParseChannel::findOrFail($channelId);
            \Log::info('Начинаем получение сообщений для канала:', [
                'id' => $channel->id,
                'channel_id' => $channel->channel_id,
                'username' => $channel->username
            ]);

            // Проверяем авторизацию
            $self = $this->madelineProto->getSelf();
            \Log::info('Информация о текущем пользователе:', ['self' => $self]);

            // Получаем информацию о канале через getPwrChat
            $pwrChat = $this->madelineProto->getPwrChat($channel->channel_id);
            \Log::info('Получена информация через getPwrChat:', ['pwrChat' => $pwrChat]);

            if (!isset($pwrChat['id']) || !isset($pwrChat['type']) || $pwrChat['type'] !== 'channel') {
                throw new \Exception('Не удалось получить информацию о канале');
            }

            // Формируем InputPeerChannel
            $inputPeer = [
                '_' => 'inputPeerChannel',
                'channel_id' => abs($pwrChat['id']),
                'access_hash' => $pwrChat['access_hash'] ?? 0
            ];

            \Log::info('Сформирован InputPeerChannel:', ['inputPeer' => $inputPeer]);

            // Получаем сообщения
            $messages = $this->madelineProto->messages->getHistory([
                'peer' => $inputPeer,
                'offset_id' => 0,
                'offset_date' => $dateFrom ? strtotime($dateFrom) : 0,
                'add_offset' => 0,
                'limit' => $limit,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);

            \Log::info('Получены сообщения:', [
                'count' => isset($messages['messages']) ? count($messages['messages']) : 0
            ]);

            if (!isset($messages['messages'])) {
                throw new \Exception('Не удалось получить сообщения из канала');
            }

            // Кэшируем результат на 1 час
            $cacheKey = "telegram_messages_{$channelId}_{$dateFrom}_{$limit}";
            Cache::put($cacheKey, $messages, 3600);

            return $messages;
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении сообщений: ' . $e->getMessage());
            throw new \Exception('Ошибка при получении сообщений: ' . $e->getMessage());
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

    /**
     * Тестирование получения сообщений из канала
     *
     * @param string $channelId ID канала (username или числовой ID)
     * @return array
     * @throws \Exception
     */
    public function testChannelMessages($channelId)
    {
        try {
            // Проверяем авторизацию
            $self = $this->madelineProto->getSelf();
            \Log::info('Информация о текущем пользователе:', ['self' => $self]);

            // Получаем информацию о канале
            $channelInfo = $this->madelineProto->getInfo($channelId);
            \Log::info('Информация о канале:', ['info' => $channelInfo]);

            // Пробуем разные методы получения сообщений
            $results = [];

            // 1. Пробуем channels->getMessages
            try {
                $messages1 = $this->madelineProto->channels->getMessages([
                    'channel' => $channelId,
                    'id' => [0]
                ]);
                $results['channels_getMessages'] = [
                    'success' => true,
                    'data' => $messages1
                ];
            } catch (\Exception $e) {
                $results['channels_getMessages'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            // 2. Пробуем messages->getHistory
            try {
                $messages2 = $this->madelineProto->messages->getHistory([
                    'peer' => $channelId,
                    'offset_id' => 0,
                    'offset_date' => 0,
                    'add_offset' => 0,
                    'limit' => 10,
                    'max_id' => 0,
                    'min_id' => 0,
                    'hash' => 0
                ]);
                $results['messages_getHistory'] = [
                    'success' => true,
                    'data' => $messages2
                ];
            } catch (\Exception $e) {
                $results['messages_getHistory'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            // 3. Пробуем messages->getMessages
            try {
                $messages3 = $this->madelineProto->messages->getMessages([
                    'id' => [0]
                ]);
                $results['messages_getMessages'] = [
                    'success' => true,
                    'data' => $messages3
                ];
            } catch (\Exception $e) {
                $results['messages_getMessages'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            \Log::info('Результаты тестирования:', $results);
            return $results;

        } catch (\Exception $e) {
            \Log::error('Ошибка при тестировании: ' . $e->getMessage());
            throw new \Exception('Ошибка при тестировании: ' . $e->getMessage());
        }
    }
}
