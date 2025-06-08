<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private $apiToken;
    private $apiUrl;
    private $cachePrefix = 'telegram_parse_';
    private $rateLimit = 30; // запросов в минуту
    private $rateLimitWindow = 60; // секунд

    public function __construct()
    {
        $this->apiToken = config('services.telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->apiToken}";
    }

    /**
     * Получение сообщений из канала с учетом ограничений и кэширования
     */
    public function getChannelMessages($chatId, $dateFrom, $limit = 100)
    {
        $cacheKey = $this->cachePrefix . "messages_{$chatId}_{$dateFrom}";

        // Проверяем кэш
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Проверяем ограничение запросов
        if (!$this->checkRateLimit()) {
            throw new \Exception('Превышен лимит запросов к Telegram API');
        }

        try {
            // Получаем информацию о канале
            $chatInfo = $this->getChatInfo($chatId);

            // Получаем сообщения
            $messages = $this->fetchMessages($chatId, $dateFrom, $limit);

            // Обрабатываем медиафайлы
            $messages = $this->processMediaFiles($messages);

            // Сохраняем в кэш на 1 час
            Cache::put($cacheKey, $messages, 3600);

            return $messages;
        } catch (\Exception $e) {
            Log::error('Telegram API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Проверка ограничения запросов
     */
    private function checkRateLimit()
    {
        $key = 'telegram_rate_limit';
        $requests = Cache::get($key, []);

        // Удаляем старые запросы
        $now = time();
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return $timestamp > ($now - $this->rateLimitWindow);
        });

        // Проверяем лимит
        if (count($requests) >= $this->rateLimit) {
            return false;
        }

        // Добавляем новый запрос
        $requests[] = $now;
        Cache::put($key, $requests, $this->rateLimitWindow);

        return true;
    }

    /**
     * Получение информации о канале
     */
    private function getChatInfo($chatId)
    {
        $response = Http::get("{$this->apiUrl}/getChat", [
            'chat_id' => $chatId
        ]);

        if (!$response->successful()) {
            throw new \Exception('Ошибка получения информации о канале: ' . $response->body());
        }

        return $response->json()['result'];
    }

    /**
     * Получение сообщений из канала
     */
    private function fetchMessages($chatId, $dateFrom, $limit)
    {
        $response = Http::get("{$this->apiUrl}/getUpdates", [
            'chat_id' => $chatId,
            'offset' => -1,
            'limit' => $limit
        ]);

        if (!$response->successful()) {
            throw new \Exception('Ошибка получения сообщений: ' . $response->body());
        }

        $updates = $response->json()['result'];
        $messages = [];

        foreach ($updates as $update) {
            if (isset($update['channel_post'])) {
                $message = $update['channel_post'];
                $messageDate = date('Y-m-d H:i:s', $message['date']);

                if ($messageDate >= $dateFrom) {
                    $messages[] = $this->formatMessage($message);
                }
            }
        }

        return $messages;
    }

    /**
     * Обработка медиафайлов в сообщениях
     */
    private function processMediaFiles($messages)
    {
        foreach ($messages as &$message) {
            if (isset($message['photo'])) {
                $message['media'] = $this->downloadPhoto($message['photo']);
            } elseif (isset($message['video'])) {
                $message['media'] = $this->downloadVideo($message['video']);
            } elseif (isset($message['document'])) {
                $message['media'] = $this->downloadDocument($message['document']);
            }
        }

        return $messages;
    }

    /**
     * Скачивание фото
     */
    private function downloadPhoto($photo)
    {
        // Получаем file_id последнего (самого большого) фото
        $fileId = end($photo)['file_id'];

        // Получаем информацию о файле
        $fileInfo = $this->getFileInfo($fileId);

        // Формируем URL для скачивания
        $fileUrl = "https://api.telegram.org/file/bot{$this->apiToken}/{$fileInfo['file_path']}";

        return [
            'type' => 'photo',
            'url' => $fileUrl,
            'file_id' => $fileId
        ];
    }

    /**
     * Получение информации о файле
     */
    private function getFileInfo($fileId)
    {
        $response = Http::get("{$this->apiUrl}/getFile", [
            'file_id' => $fileId
        ]);

        if (!$response->successful()) {
            throw new \Exception('Ошибка получения информации о файле: ' . $response->body());
        }

        return $response->json()['result'];
    }

    /**
     * Форматирование сообщения
     */
    private function formatMessage($message)
    {
        return [
            'message_id' => $message['message_id'],
            'date' => date('Y-m-d H:i:s', $message['date']),
            'text' => $message['text'] ?? null,
            'caption' => $message['caption'] ?? null,
            'media' => null,
            'raw_data' => $message
        ];
    }
}
