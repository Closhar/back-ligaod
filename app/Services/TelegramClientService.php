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

    /**
     * Инициализация MadelineProto
     */
    private function initializeMadelineProto()
    {
        try {
            \Log::info('Начало инициализации MadelineProto', [
                'session_path' => $this->sessionPath,
                'api_id' => $this->apiId,
                'api_hash' => substr($this->apiHash, 0, 5) . '...' // Логируем только часть хэша для безопасности
            ]);

            if (!file_exists($this->sessionPath)) {
                \Log::info('Файл сессии не найден, создаем новый');
                $settings = [
                    'app_info' => [
                        'api_id' => $this->apiId,
                        'api_hash' => $this->apiHash,
                    ],
                    'logger' => [
                        'logger' => 'file',
                        'logger_level' => 'verbose',
                        'logger_path' => storage_path('logs/madeline-' . date('Y-m-d') . '.log'),
                    ],
                    'serialization' => [
                        'serialization_interval' => 30,
                        'cleanup_before_serialization' => true,
                    ],
                ];

                $this->madelineProto = new \danog\MadelineProto\API($this->sessionPath, $settings);
                \Log::info('MadelineProto успешно инициализирован с новыми настройками');
            } else {
                \Log::info('Файл сессии найден, загружаем существующую сессию');
                $this->madelineProto = new \danog\MadelineProto\API($this->sessionPath);
                \Log::info('Существующая сессия MadelineProto успешно загружена');
            }

            // Проверяем авторизацию
            try {
                $self = $this->madelineProto->getSelf();
                \Log::info('Авторизация подтверждена', [
                    'user_id' => $self['id'] ?? null,
                    'username' => $self['username'] ?? null
                ]);
            } catch (\Exception $e) {
                \Log::error('Ошибка при проверке авторизации: ' . $e->getMessage());
                throw new \Exception('Ошибка авторизации в Telegram: ' . $e->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Ошибка при инициализации MadelineProto: ' . $e->getMessage());
            \Log::error('Трейс ошибки: ' . $e->getTraceAsString());
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
            if (!$this->madelineProto) {
                throw new \Exception('MadelineProto не инициализирован');
            }

            // Получаем информацию о канале через getFullInfo
            $channelInfo = $this->madelineProto->getFullInfo($channelId);
            Log::info('Получена информация о канале:', ['channelInfo' => $channelInfo]);

            if (!$channelInfo || !isset($channelInfo['Chat'])) {
                throw new \Exception('Не удалось получить информацию о канале');
            }

            $chat = $channelInfo['Chat'];

            return [
                'id' => $chat['id'],
                'title' => $chat['title'],
                'username' => $chat['username'] ?? null,
                'participants_count' => $chat['participants_count'] ?? null,
                'description' => $chat['about'] ?? null,
                'photo' => $chat['photo'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о канале: ' . $e->getMessage());
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

            // Проверяем и переинициализируем MadelineProto при необходимости
            if (!$this->madelineProto) {
                \Log::info('MadelineProto не инициализирован, выполняем инициализацию');
                $this->initializeMadelineProto();
            }

            // Проверяем состояние сессии
            try {
                $fullInfo = $this->madelineProto->getFullInfo('me');
                \Log::info('Состояние сессии:', [
                    'authorized' => $fullInfo['authorized'] ?? false,
                    'user_id' => $fullInfo['user']['id'] ?? null,
                    'username' => $fullInfo['user']['username'] ?? null
                ]);
            } catch (\Exception $e) {
                \Log::warning('Ошибка при проверке состояния сессии, пробуем переинициализировать: ' . $e->getMessage());
                $this->initializeMadelineProto();
            }

            // Получаем информацию о канале
            try {
                $channelInfo = $this->getChannelInfo($channelId);
                \Log::info('Информация о канале получена:', [
                    'channel_id' => $channelInfo['id'] ?? null,
                    'title' => $channelInfo['title'] ?? null,
                    'username' => $channelInfo['username'] ?? null,
                    'participants_count' => $channelInfo['participants_count'] ?? null
                ]);
            } catch (\Exception $e) {
                \Log::error('Ошибка при получении информации о канале: ' . $e->getMessage());
                throw $e;
            }

            // Получаем сообщения с повторными попытками
            $maxRetries = 3;
            $retryCount = 0;
            $messages = null;
            $lastError = null;

            while ($retryCount < $maxRetries) {
                try {
                    \Log::info('Попытка получения сообщений #' . ($retryCount + 1), [
                        'channel_id' => $channelId,
                        'offset' => $offset,
                        'limit' => $limit
                    ]);

                    // Проверяем состояние соединения перед запросом
                    if (!$this->madelineProto) {
                        \Log::warning('MadelineProto не инициализирован перед запросом, переинициализируем');
                        $this->initializeMadelineProto();
                    }

                    $messages = $this->madelineProto->messages->getHistory([
                        'peer' => $channelId,
                        'offset_id' => 0,
                        'offset_date' => 0,
                        'add_offset' => $offset,
                        'limit' => $limit * 2,
                        'max_id' => 0,
                        'min_id' => 0,
                        'hash' => 0
                    ]);

                    \Log::info('Ответ от Telegram API получен', [
                        'has_messages' => isset($messages['messages']),
                        'messages_count' => isset($messages['messages']) ? count($messages['messages']) : 0,
                        'response_keys' => array_keys($messages)
                    ]);

                    if (isset($messages['messages']) && !empty($messages['messages'])) {
                        \Log::info('Сообщения успешно получены', [
                            'count' => count($messages['messages']),
                            'first_message_id' => $messages['messages'][0]['id'] ?? null,
                            'last_message_id' => end($messages['messages'])['id'] ?? null,
                            'first_message_date' => isset($messages['messages'][0]['date']) ? date('Y-m-d H:i:s', $messages['messages'][0]['date']) : null,
                            'last_message_date' => isset(end($messages['messages'])['date']) ? date('Y-m-d H:i:s', end($messages['messages'])['date']) : null
                        ]);
                        break;
                    } else {
                        \Log::warning('Получен пустой список сообщений, пробуем еще раз', [
                            'response' => $messages
                        ]);
                        $retryCount++;
                        if ($retryCount < $maxRetries) {
                            sleep(1);
                        }
                    }
                } catch (\Exception $e) {
                    $lastError = $e;
                    \Log::error('Ошибка при получении сообщений (попытка #' . ($retryCount + 1) . '): ' . $e->getMessage(), [
                        'exception_class' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Если ошибка связана с закрытым соединением, пробуем переинициализировать
                    if (strpos($e->getMessage(), 'channel was already closed') !== false) {
                        \Log::warning('Обнаружено закрытое соединение, пробуем переинициализировать MadelineProto');
                        $this->initializeMadelineProto();
                    }

                    $retryCount++;
                    if ($retryCount < $maxRetries) {
                        sleep(1);
                    } else {
                        throw new \Exception('Не удалось получить сообщения после ' . $maxRetries . ' попыток. Последняя ошибка: ' . $e->getMessage());
                    }
                }
            }

            if (!isset($messages['messages']) || empty($messages['messages'])) {
                \Log::error('Не удалось получить сообщения после всех попыток', [
                    'channel_id' => $channelId,
                    'attempts' => $retryCount,
                    'last_error' => $lastError ? $lastError->getMessage() : null,
                    'last_response' => $messages
                ]);
                throw new \Exception('Не удалось получить сообщения из канала');
            }

            // Фильтруем и сортируем сообщения
            $filteredMessages = [];
            $count = 0;
            $dateFromTimestamp = $dateFrom ? strtotime($dateFrom) : null;

            \Log::info('Начинаем фильтрацию сообщений', [
                'total_messages' => count($messages['messages']),
                'date_from_timestamp' => $dateFromTimestamp,
                'date_from_formatted' => $dateFromTimestamp ? date('Y-m-d H:i:s', $dateFromTimestamp) : null
            ]);

            // Сортируем сообщения по дате (от ранних к поздним)
            usort($messages['messages'], function($a, $b) {
                return ($a['date'] ?? 0) - ($b['date'] ?? 0);
            });

            foreach ($messages['messages'] as $message) {
                // Пропускаем служебные сообщения
                if (!isset($message['message']) && !isset($message['media'])) {
                    \Log::debug('Пропущено служебное сообщение', [
                        'message_id' => $message['id'] ?? null,
                        'message_type' => isset($message['message']) ? 'text' : (isset($message['media']) ? 'media' : 'unknown')
                    ]);
                    continue;
                }

                // Проверяем дату, если указана
                if ($dateFromTimestamp && ($message['date'] ?? 0) < $dateFromTimestamp) {
                    \Log::debug('Пропущено сообщение по дате', [
                        'message_id' => $message['id'] ?? null,
                        'message_date' => isset($message['date']) ? date('Y-m-d H:i:s', $message['date']) : null,
                        'date_from' => date('Y-m-d H:i:s', $dateFromTimestamp)
                    ]);
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

            \Log::info('Фильтрация сообщений завершена', [
                'channel_id' => $channelId,
                'total_messages' => count($messages['messages']),
                'filtered_messages' => count($filteredMessages),
                'has_more' => $hasMore,
                'next_offset' => $nextOffset,
                'first_message_date' => !empty($filteredMessages) ? date('Y-m-d H:i:s', $filteredMessages[0]['date']) : null,
                'last_message_date' => !empty($filteredMessages) ? date('Y-m-d H:i:s', end($filteredMessages)['date']) : null
            ]);

            return [
                'messages' => $filteredMessages,
                'has_more' => $hasMore,
                'next_offset' => $nextOffset
            ];

        } catch (\Exception $e) {
            \Log::error('Ошибка при получении сообщений: ' . $e->getMessage(), [
                'channel_id' => $channelId,
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
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

    /**
     * Проверка доступа к каналу
     */
    public function checkChannelAccess($channelId)
    {
        try {
            // Убираем @ из начала channelId, если он есть
            $channelId = ltrim($channelId, '@');

            \Log::info('Проверка доступа к каналу', ['channel_id' => $channelId]);

            // Проверяем авторизацию
            if (!$this->madelineProto) {
                throw new \Exception('MadelineProto не инициализирован');
            }

            // Пробуем получить информацию о канале
            $channelInfo = $this->getChannelInfo($channelId);
            if (!$channelInfo) {
                throw new \Exception('Не удалось получить информацию о канале');
            }

            // Пробуем получить одно сообщение для проверки доступа
            $messages = $this->madelineProto->messages->getHistory([
                'peer' => $channelId,
                'offset_id' => 0,
                'offset_date' => 0,
                'add_offset' => 0,
                'limit' => 1,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);

            \Log::info('Доступ к каналу подтвержден', [
                'channel_id' => $channelId,
                'channel_info' => $channelInfo,
                'has_messages' => isset($messages['messages']) && !empty($messages['messages'])
            ]);

            return [
                'success' => true,
                'channel_info' => $channelInfo,
                'has_access' => true,
                'has_messages' => isset($messages['messages']) && !empty($messages['messages'])
            ];

        } catch (\Exception $e) {
            \Log::error('Ошибка при проверке доступа к каналу: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'has_access' => false
            ];
        }
    }
}
