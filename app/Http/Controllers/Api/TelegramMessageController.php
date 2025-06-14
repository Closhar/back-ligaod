<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramParseChannel;
use App\Models\TelegramMessage;
use App\Services\TelegramClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TelegramMessageController extends Controller
{
    private $telegramClientService;

    public function __construct(TelegramClientService $telegramClientService)
    {
        $this->telegramClientService = $telegramClientService;
    }

    /**
     * Получить сообщения из канала
     */
    public function fetchMessages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'channel_id' => 'nullable|integer|exists:telegram_parse_channels,id',
                'channel_usernames' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
                'date_from' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Проверяем наличие хотя бы одного из параметров
            if (!$request->has('channel_id') && !$request->has('channel_usernames')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Необходимо указать channel_id или channel_usernames'
                ], 422);
            }

            $allMessages = [];
            $errors = [];

            // Если передан channel_usernames, используем его
            if ($request->has('channel_usernames')) {
                // Разбиваем строку channel_usernames на массив и очищаем от @ если есть
                $usernames = array_map(function($username) {
                    return trim(str_replace('@', '', $username));
                }, explode(',', $request->channel_usernames));

                foreach ($usernames as $username) {
                    try {
                        $messages = $this->processSingleChannel($username, $request);
                        $allMessages = array_merge($allMessages, $messages);
                    } catch (\Exception $e) {
                        $errors[] = [
                            'username' => $username,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            } else {
                // Если передан channel_id, получаем username из базы и парсим канал
                try {
                    $channel = TelegramParseChannel::findOrFail($request->channel_id);
                    $messages = $this->processSingleChannel($channel->username, $request);
                    $allMessages = array_merge($allMessages, $messages);
                } catch (\Exception $e) {
                    $errors[] = [
                        'channel_id' => $request->channel_id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Сортируем все сообщения по дате
            $allMessages = collect($allMessages)->sortBy(function($message) {
                return strtotime($message['date']);
            })->values()->all();

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $allMessages,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Общая ошибка: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении сообщений',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обработка одного канала по username
     */
    private function processSingleChannel($username, Request $request)
    {
        \Log::info('Начало получения сообщений', [
            'channel_username' => $username,
            'limit' => $request->limit,
            'offset' => $request->offset,
            'date_from' => $request->date_from
        ]);

        $telegramService = app(TelegramClientService::class);

        try {
            // Получаем сообщения напрямую по username
            $messages = $telegramService->getChannelMessages(
                $username,
                $request->limit ?? 50,
                $request->offset ?? 0,
                $request->date_from
            );

            // Форматируем сообщения
            $formattedMessages = array_map(function($message) use ($username) {
                return [
                    'channel' => $username,
                    'date' => $message['date'] ?? null,
                    'message' => $message['message'] ?? null,
                    'message_id' => $message['id'] ?? null,
                    'views' => $message['views'] ?? null,
                    'forwards' => $message['forwards'] ?? null
                ];
            }, $messages['messages']);

            return $formattedMessages;

        } catch (\Exception $e) {
            \Log::error('Ошибка при получении сообщений: ' . $e->getMessage());
            throw $e;
        }
    }
}
