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

            // Если передан channel_usernames, используем его
            if ($request->has('channel_usernames')) {
                // Разбиваем строку channel_usernames на массив и очищаем от @ если есть
                $usernames = array_map(function($username) {
                    return trim(str_replace('@', '', $username));
                }, explode(',', $request->channel_usernames));

                $results = [];
                $errors = [];

                foreach ($usernames as $username) {
                    try {
                        $result = $this->processSingleChannel($username, $request);
                        $results[] = $result;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'username' => $username,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'results' => $results,
                        'errors' => $errors
                    ]
                ]);
            }

            // Если передан только channel_id, используем старую логику
            $channel = TelegramParseChannel::findOrFail($request->channel_id);

            \Log::info('Начало получения сообщений', [
                'channel_id' => $channel->id,
                'channel_username' => $channel->channel_id,
                'limit' => $request->limit,
                'offset' => $request->offset,
                'date_from' => $request->date_from
            ]);

            $telegramService = app(TelegramClientService::class);

            // Получаем сообщения
            try {
                $messages = $telegramService->getChannelMessages(
                    $channel->channel_id,
                    $request->limit ?? 50,
                    $request->offset ?? 0,
                    $request->date_from
                );
                \Log::info('Получены сообщения:', ['messages' => $messages]);
            } catch (\Exception $e) {
                \Log::error('Ошибка при получении сообщений: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении сообщений',
                    'error' => $e->getMessage()
                ], 500);
            }

            // Обновляем статистику канала
            $channel->update([
                'last_parse_at' => now(),
                'parse_status' => 'success',
                'error_message' => null
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => [
                        'id' => $channel->id,
                        'title' => $channel->title,
                        'username' => $channel->username,
                        'channel_id' => $channel->channel_id
                    ],
                    'messages' => $messages['messages'],
                    'pagination' => [
                        'has_more' => $messages['has_more'],
                        'next_offset' => $messages['next_offset']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Общая ошибка: ' . $e->getMessage());

            // Если канал найден, обновляем его статистику с ошибкой
            if (isset($channel)) {
                $channel->update([
                    'last_parse_at' => now(),
                    'parse_status' => 'error',
                    'error_message' => $e->getMessage()
                ]);
            }

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
        // Получаем канал из базы данных по username
        $channel = TelegramParseChannel::where('username', $username)
            ->orWhere('channel_id', $username)
            ->firstOrFail();

        \Log::info('Начало получения сообщений', [
            'channel_id' => $channel->id,
            'channel_username' => $channel->username,
            'limit' => $request->limit,
            'offset' => $request->offset,
            'date_from' => $request->date_from
        ]);

        $telegramService = app(TelegramClientService::class);

        // Получаем сообщения
        $messages = $telegramService->getChannelMessages(
            $channel->channel_id,
            $request->limit ?? 50,
            $request->offset ?? 0,
            $request->date_from
        );

        // Обновляем статистику канала
        $channel->update([
            'last_parse_at' => now(),
            'parse_status' => 'success',
            'error_message' => null
        ]);

        return [
            'channel' => [
                'id' => $channel->id,
                'title' => $channel->title,
                'username' => $channel->username,
                'channel_id' => $channel->channel_id
            ],
            'messages' => $messages['messages'],
            'pagination' => [
                'has_more' => $messages['has_more'],
                'next_offset' => $messages['next_offset']
            ]
        ];
    }
}
