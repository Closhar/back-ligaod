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

            // Полностью отключаем логирование
            $logger = new Logger;
            $logger->setLevel(5); // FATAL_ERROR - максимальный уровень, фактически отключает логирование
            $logger->setExtra(storage_path('logs/madeline.log')); // Указываем путь к лог-файлу в storage
            $settings->setLogger($logger);

            // Настройки приложения
            $appInfo = new AppInfo;
            $appInfo->setApiId($this->apiId);
            $appInfo->setApiHash($this->apiHash);
            $settings->setAppInfo($appInfo);

            // Инициализация MadelineProto с отключенным логированием
            $this->madelineProto = new API($this->sessionPath, $settings);

            Log::info('MadelineProto успешно инициализирован');
        } catch (\Exception $e) {
            Log::error('Ошибка инициализации MadelineProto: ' . $e->getMessage());
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
    public function getChannelMessages($channelId, $limit = 10, $offset = 0, $dateFrom = null)
    {
        try {
            // Убираем @ если он есть в начале
            $channelId = ltrim($channelId, '@');

            // Проверяем авторизацию
            if (!$this->madelineProto) {
                throw new \Exception('MadelineProto не инициализирован');
            }

            // Получаем информацию о канале для проверки ID
            $channelInfo = $this->getChannelInfo($channelId);
            $channelNumericId = $channelInfo['id'];

            // Конвертируем дату в timestamp если она передана
            $dateFromTimestamp = $dateFrom ? strtotime($dateFrom) : null;

            // Получаем сообщения из канала через messages.getHistory
            $messages = $this->madelineProto->messages->getHistory([
                'peer' => $channelId,
                'limit' => $limit,
                'offset_id' => $offset,
                'offset_date' => $dateFromTimestamp,
                'add_offset' => 0,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);

            $result = [];
            if (isset($messages['messages'])) {
                foreach ($messages['messages'] as $message) {
                    if ($dateFromTimestamp && $message['date'] < $dateFromTimestamp) {
                        continue;
                    }

                    $result[] = [
                        'message_id' => $message['id'],
                        'date' => date('Y-m-d H:i:s', $message['date']),
                        'text' => $message['message'] ?? null,
                        'caption' => $message['caption'] ?? null,
                        'photo' => $message['media']['photo'] ?? null,
                        'video' => $message['media']['document'] ?? null,
                        'document' => $message['media']['document'] ?? null,
                        'entities' => $message['entities'] ?? [],
                        'link_preview' => $message['media']['webpage'] ?? null,
                    ];

                    if (count($result) >= $limit) {
                        break;
                    }
                }
            }

            // Сортируем сообщения по дате (новые сверху)
            usort($result, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            return $result;
        } catch (\Exception $e) {
            Log::error('Ошибка при получении сообщений: ' . $e->getMessage());
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
