<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Connection;
use danog\MadelineProto\Settings\Logger;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Logger as MadelineLogger;

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
                chmod($sessionDir, 0777);
            }

            // Настройки MadelineProto
            $settings = new Settings;

            // Настройки логгера - используем кастомный логгер
            $logger = new Logger;
            $logger->setLevel(3); // 3 = WARNING level
            $logger->setType(MadelineLogger::LOGGER_CALLABLE);
            $logger->setExtra(function($message, $level) {
                switch ($level) {
                    case MadelineLogger::VERBOSE:
                        Log::debug($message);
                        break;
                    case MadelineLogger::NOTICE:
                        Log::info($message);
                        break;
                    case MadelineLogger::WARNING:
                        Log::warning($message);
                        break;
                    case MadelineLogger::ERROR:
                        Log::error($message);
                        break;
                    case MadelineLogger::FATAL_ERROR:
                        Log::critical($message);
                        break;
                }
            });
            $settings->setLogger($logger);

            // Настройки приложения
            $appInfo = new AppInfo;
            $appInfo->setApiId($this->apiId);
            $appInfo->setApiHash($this->apiHash);
            $settings->setAppInfo($appInfo);

            // Инициализация MadelineProto
            $this->madelineProto = new API($this->sessionPath, $settings);

            Log::info('MadelineProto успешно инициализирован');
        } catch (\Exception $e) {
            Log::error('Ошибка инициализации MadelineProto: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получить информацию о текущем пользователе
     */
    public function getSelf()
    {
        try {
            return $this->madelineProto->getSelf();
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о пользователе: ' . $e->getMessage());
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

            // Получаем информацию о канале
            $channelInfo = $this->madelineProto->channels->getChannels(['id' => [$channelId]])['chats'][0];

            return [
                'id' => $channelInfo['id'],
                'title' => $channelInfo['title'],
                'username' => $channelInfo['username'] ?? null,
                'participants_count' => $channelInfo['participants_count'] ?? null,
                'description' => $channelInfo['about'] ?? null,
                'photo' => $channelInfo['photo'] ?? null,
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

            // Получаем информацию о канале для проверки ID
            $channelInfo = $this->getChannelInfo($channelId);
            $channelNumericId = $channelInfo['id'];

            // Конвертируем дату в timestamp если она передана
            $dateFromTimestamp = $dateFrom ? strtotime($dateFrom) : null;

            // Получаем сообщения из канала
            $messages = $this->madelineProto->channels->getHistory([
                'channel' => $channelId,
                'limit' => $limit,
                'offset_id' => $offset,
                'offset_date' => $dateFromTimestamp,
                'add_offset' => 0,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);

            $result = [];
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
