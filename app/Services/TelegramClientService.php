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
                $result = $response->json()['result'];

                // Форматируем результат
                return [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'username' => $result['username'],
                    'type' => $result['type'],
                    'description' => $result['description'] ?? null,
                    'members_count' => $result['members_count'] ?? null,
                    'photo' => $result['photo'] ?? null,
                    'pinned_message' => $result['pinned_message'] ?? null,
                ];
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

            // Получаем информацию о канале для проверки ID
            $channelInfo = $this->getChannelInfo($channelId);
            $channelNumericId = $channelInfo['id'];

            // Получаем сообщения через getUpdates
            $response = Http::get("{$this->baseUrl}/bot{$this->apiHash}/getUpdates", [
                'limit' => 100, // Получаем больше сообщений для фильтрации
                'offset' => $offset
            ]);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['ok']) && $result['ok']) {
                    // Фильтруем сообщения только из нужного канала
                    $messages = [];
                    foreach ($result['result'] as $update) {
                        if (isset($update['channel_post']) &&
                            isset($update['channel_post']['chat']) &&
                            $update['channel_post']['chat']['id'] == $channelNumericId) {

                            $post = $update['channel_post'];
                            $messages[] = [
                                'message_id' => $post['message_id'],
                                'date' => date('Y-m-d H:i:s', $post['date']),
                                'text' => $post['text'] ?? null,
                                'caption' => $post['caption'] ?? null,
                                'photo' => $post['photo'] ?? null,
                                'video' => $post['video'] ?? null,
                                'document' => $post['document'] ?? null,
                                'entities' => $post['entities'] ?? [],
                                'link_preview' => $post['link_preview_options'] ?? null,
                            ];

                            // Ограничиваем количество сообщений
                            if (count($messages) >= $limit) {
                                break;
                            }
                        }
                    }
                    return $messages;
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
                    return [
                        'id' => $result['result']['id'],
                        'username' => $result['result']['username'],
                        'first_name' => $result['result']['first_name'],
                        'can_join_groups' => $result['result']['can_join_groups'] ?? false,
                        'can_read_all_group_messages' => $result['result']['can_read_all_group_messages'] ?? false,
                        'supports_inline_queries' => $result['result']['supports_inline_queries'] ?? false,
                    ];
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
                'members_count' => $info['members_count'] ?? null,
                'description' => $info['description'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Ошибка при получении статистики канала: ' . $e->getMessage());
            throw $e;
        }
    }
}
