<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Logger;
use danog\MadelineProto\Settings\AppInfo;

class TelegramClientService
{
    protected $apiId;
    protected $apiHash;
    protected $sessionPath;
    protected $madelineProto;

    public function __construct()
    {
        $this->apiId = config('services.telegram.api_id');
        $this->apiHash = config('services.telegram.api_hash');
        $this->sessionPath = storage_path('madeline/madeline.madeline');

        $this->initializeMadelineProto();
    }

    protected function initializeMadelineProto()
    {
        try {
            // Создаем директорию для сессии если её нет
            $sessionDir = dirname($this->sessionPath);
            if (!file_exists($sessionDir)) {
                if (!mkdir($sessionDir, 0777, true)) {
                    throw new \Exception("Не удалось создать директорию для сессии: {$sessionDir}");
                }
            }

            // Проверяем права на запись в директорию
            if (!is_writable($sessionDir)) {
                throw new \Exception("Нет прав на запись в директорию: {$sessionDir}");
            }

            // Настройки MadelineProto
            $settings = new Settings;

            // Настройки логирования
            $logger = new Logger;
            $logger->setLevel(5); // Устанавливаем уровень логирования (5 = FATAL_ERROR)
            $logger->setExtra(storage_path('logs/madeline.log'));
            $settings->setLogger($logger);

            // Настройки приложения
            $appInfo = new AppInfo;
            $appInfo->setApiId($this->apiId);
            $appInfo->setApiHash($this->apiHash);
            $settings->setAppInfo($appInfo);

            // Инициализация MadelineProto
            $this->madelineProto = new API($this->sessionPath, $settings);

            // Проверяем авторизацию
            try {
                $self = $this->madelineProto->getSelf();
                if (!$self) {
                    // Если не авторизованы, запускаем процесс авторизации
                    $this->madelineProto->start();
                }
            } catch (\Exception $e) {
                // Если возникла ошибка при проверке авторизации, пробуем авторизоваться
                $this->madelineProto->start();
            }

            Log::info('MadelineProto успешно инициализирован');
        } catch (\Exception $e) {
            Log::error('Ошибка инициализации MadelineProto: ' . $e->getMessage());
            Log::error('Трейс ошибки: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Авторизация в Telegram
     */
    public function login()
    {
        try {
            Log::info('Начинаем процесс авторизации в Telegram');

            // Проверяем, существует ли файл сессии
            if (file_exists($this->sessionPath)) {
                Log::info('Найдена существующая сессия, пробуем использовать её');
                try {
                    $self = $this->madelineProto->getSelf();
                    if ($self) {
                        Log::info('Успешно авторизованы через существующую сессию');
                        return true;
                    }
                } catch (\Exception $e) {
                    Log::warning('Не удалось использовать существующую сессию: ' . $e->getMessage());
                }
            }

            // Если сессия не существует или не работает, начинаем новую авторизацию
            Log::info('Начинаем новую авторизацию');

            // Запускаем процесс авторизации
            $this->madelineProto->start();

            // Проверяем результат авторизации
            $self = $this->madelineProto->getSelf();
            if (!$self) {
                throw new \Exception('Не удалось получить информацию о пользователе после авторизации');
            }

            Log::info('Успешно авторизованы в Telegram');
            return true;

        } catch (\Exception $e) {
            Log::error('Ошибка при авторизации: ' . $e->getMessage());
            Log::error('Трейс ошибки: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Получить информацию о текущем пользователе
     */
    public function getSelf()
    {
        try {
            Log::info('Начинаем получение информации о пользователе');

            // Проверяем авторизацию
            if (!$this->madelineProto) {
                throw new \Exception('MadelineProto не инициализирован');
            }

            // Пробуем получить информацию о пользователе
            $self = $this->madelineProto->getSelf();
            Log::info('Получена информация о пользователе:', ['self' => $self]);

            if (!$self) {
                // Если не авторизованы, пробуем авторизоваться
                Log::info('Пользователь не авторизован, пробуем авторизоваться');
                $this->login();

                // После авторизации пробуем снова получить информацию
                $self = $this->madelineProto->getSelf();
                if (!$self) {
                    throw new \Exception('Не удалось получить информацию о пользователе после авторизации');
                }
            }

            // Формируем ответ
            $result = [
                'id' => $self['id'] ?? null,
                'first_name' => $self['first_name'] ?? null,
                'last_name' => $self['last_name'] ?? null,
                'username' => $self['username'] ?? null,
                'phone' => $self['phone'] ?? null,
                'status' => $self['status'] ?? null,
            ];

            Log::info('Сформирован результат:', ['result' => $result]);
            return $result;

        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о пользователе: ' . $e->getMessage());
            Log::error('Трейс ошибки: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Получить информацию о канале
     */
    public function getChannelInfo($channelId)
    {
        try {
            // Убираем @ если он есть в начале
            $channelId = ltrim($channelId, '@');

            // Проверяем авторизацию
            try {
                $self = $this->madelineProto->getSelf();
                if (!$self) {
                    $this->login();
                }
            } catch (\Exception $e) {
                $this->login();
            }

            // Пробуем разные форматы идентификатора канала
            $channelIdentifiers = [
                $channelId,                    // zenit2fc
                '@' . $channelId,              // @zenit2fc
                'https://t.me/' . $channelId,  // https://t.me/zenit2fc
                't.me/' . $channelId,          // t.me/zenit2fc
            ];

            $lastError = null;
            foreach ($channelIdentifiers as $identifier) {
                try {
                    // Получаем информацию о канале через getFullInfo
                    $channelInfo = $this->madelineProto->getFullInfo($identifier);
                    if ($channelInfo && isset($channelInfo['Chat'])) {
                        $chat = $channelInfo['Chat'];
                        return [
                            'id' => $chat['id'],
                            'title' => $chat['title'],
                            'username' => $chat['username'] ?? null,
                            'participants_count' => $chat['participants_count'] ?? null,
                            'description' => $chat['about'] ?? null,
                            'photo' => $chat['photo'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    $lastError = $e;
                    \Log::warning("Не удалось получить информацию для идентификатора {$identifier}: " . $e->getMessage());
                    continue;
                }
            }

            throw $lastError ?? new \Exception('Не удалось получить информацию о канале');
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении информации о канале: ' . $e->getMessage());
            \Log::error('Трейс ошибки: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Получить сообщения из канала
     */
    public function getChannelMessages($channelId, $limit = 50, $offset = 0, $dateFrom = null)
    {
        try {
            // Убираем @ из начала channelId, если он есть
            $channelId = ltrim($channelId, '@');

            \Log::info('Начало получения сообщений из канала', [
                'channel_id' => $channelId,
                'limit' => $limit,
                'offset' => $offset,
                'date_from' => $dateFrom
            ]);

            // Проверяем авторизацию
            try {
                $self = $this->madelineProto->getSelf();
                if (!$self) {
                    $this->login();
                }
            } catch (\Exception $e) {
                $this->login();
            }

            // Пробуем разные форматы идентификатора канала
            $channelIdentifiers = [
                $channelId,                    // zenit2fc
                '@' . $channelId,              // @zenit2fc
                'https://t.me/' . $channelId,  // https://t.me/zenit2fc
                't.me/' . $channelId,          // t.me/zenit2fc
            ];

            $lastError = null;
            $messages = null;

            foreach ($channelIdentifiers as $identifier) {
                try {
                    // Получаем информацию о канале
                    $channelInfo = $this->madelineProto->getFullInfo($identifier);
                    if (!$channelInfo || !isset($channelInfo['Chat'])) {
                        continue;
                    }

                    // Получаем сообщения
                    $messages = $this->madelineProto->messages->getHistory([
                        'peer' => $identifier,
                        'offset_id' => 0,
                        'offset_date' => 0,
                        'add_offset' => $offset,
                        'limit' => $limit * 2, // Запрашиваем больше сообщений для фильтрации
                        'max_id' => 0,
                        'min_id' => 0,
                        'hash' => 0
                    ]);

                    if (isset($messages['messages']) && !empty($messages['messages'])) {
                        break;
                    }
                } catch (\Exception $e) {
                    $lastError = $e;
                    \Log::warning("Не удалось получить сообщения для идентификатора {$identifier}: " . $e->getMessage());
                    continue;
                }
            }

            if (!isset($messages['messages']) || empty($messages['messages'])) {
                throw $lastError ?? new \Exception('Не удалось получить сообщения из канала');
            }

            // Фильтруем и сортируем сообщения
            $filteredMessages = [];
            $count = 0;
            $dateFromTimestamp = $dateFrom ? strtotime($dateFrom) : null;

            // Сортируем сообщения по дате (от ранних к поздним)
            usort($messages['messages'], function($a, $b) {
                return ($a['date'] ?? 0) - ($b['date'] ?? 0);
            });

            foreach ($messages['messages'] as $message) {
                // Пропускаем служебные сообщения
                if (!isset($message['message']) && !isset($message['media'])) {
                    continue;
                }

                // Проверяем дату, если указана
                if ($dateFromTimestamp && ($message['date'] ?? 0) < $dateFromTimestamp) {
                    continue;
                }

                // Формируем данные сообщения
                $messageData = [
                    'id' => $message['id'],
                    'date' => $message['date'] ?? null,
                    'message' => $message['message'] ?? null,
                    'media' => $message['media'] ?? null,
                    'views' => $message['views'] ?? 0,
                    'forwards' => $message['forwards'] ?? 0,
                    'reactions' => $message['reactions'] ?? null
                ];

                $filteredMessages[] = $messageData;
                $count++;

                // Прерываем, если достигли лимита
                if ($count >= $limit) {
                    break;
                }
            }

            // Определяем, есть ли еще сообщения
            $hasMore = count($messages['messages']) > $count;
            $nextOffset = $hasMore ? $offset + $count : null;

            \Log::info('Успешно получены сообщения', [
                'channel_id' => $channelId,
                'total_messages' => count($messages['messages']),
                'filtered_messages' => count($filteredMessages),
                'has_more' => $hasMore,
                'next_offset' => $nextOffset
            ]);

            return [
                'messages' => $filteredMessages,
                'has_more' => $hasMore,
                'next_offset' => $nextOffset
            ];

        } catch (\Exception $e) {
            \Log::error('Ошибка при получении сообщений: ' . $e->getMessage());
            \Log::error('Трейс ошибки: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Отправить сообщение
     */
    public function sendMessage($chatId, $text, $parseMode = 'HTML')
    {
        try {
            $response = Http::post("{$this->baseUrl}/bot{$this->apiHash}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode
            ]);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['ok']) && $result['ok']) {
                    return [
                        'message_id' => $result['result']['message_id'],
                        'date' => date('Y-m-d H:i:s', $result['result']['date']),
                        'text' => $result['result']['text'],
                        'chat' => $result['result']['chat'],
                    ];
                }
            }

            throw new \Exception("Не удалось отправить сообщение: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке сообщения: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получить статистику канала
     */
    public function getChannelStats($channelId)
    {
        try {
            $info = $this->getChannelInfo($channelId);
            $messages = $this->getChannelMessages($channelId, 1);

            return [
                'channel_info' => $info,
                'last_message' => $messages[0] ?? null,
                'members_count' => $info['participants_count'] ?? null,
                'description' => $info['description'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Ошибка при получении статистики канала: ' . $e->getMessage());
            throw $e;
        }
    }
}
