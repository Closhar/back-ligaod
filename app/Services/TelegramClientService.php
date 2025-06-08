<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramClientService
{
    protected $apiId;
    protected $apiHash;
    protected $baseUrl = 'https://api.telegram.org';

    public function __construct()
    {
        $this->apiId = config('services.telegram.api_id');
        $this->apiHash = config('services.telegram.api_hash');
    }

    /**
     * Получить информацию о канале
     */
    public function getChannelInfo($channelId)
    {
        try {
            // Убираем @ если он есть в начале
            $channelId = ltrim($channelId, '@');

            // Пробуем получить информацию через getChat
            $response = Http::get("{$this->baseUrl}/bot{$this->apiHash}/getChat", [
                'chat_id' => "@{$channelId}"
            ]);

            if ($response->successful()) {
                return $response->json()['result'];
            }

            throw new \Exception("Не удалось получить информацию о канале: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Ошибка при получении информации о канале: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получить сообщения из канала
     */
    public function getChannelMessages($channelId, $limit = 10, $offset = 0)
    {
        try {
            // Убираем @ если он есть в начале
            $channelId = ltrim($channelId, '@');

            // Получаем сообщения через getUpdates
            $response = Http::get("{$this->baseUrl}/bot{$this->apiHash}/getUpdates", [
                'chat_id' => "@{$channelId}",
                'limit' => $limit,
                'offset' => $offset
            ]);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['ok']) && $result['ok']) {
                    return $result['result'];
                }
            }

            throw new \Exception("Не удалось получить сообщения: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Ошибка при получении сообщений: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Проверить авторизацию
     */
    public function getSelf()
    {
        try {
            $response = Http::get("{$this->baseUrl}/bot{$this->apiHash}/getMe");

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['ok']) && $result['ok']) {
                    return $result['result'];
                }
            }

            throw new \Exception("Не удалось получить информацию о боте: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Ошибка при проверке авторизации: ' . $e->getMessage());
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
                    return $result['result'];
                }
            }

            throw new \Exception("Не удалось отправить сообщение: " . $response->body());
        } catch (\Exception $e) {
            Log::error('Ошибка при отправке сообщения: ' . $e->getMessage());
            throw $e;
        }
    }
}
