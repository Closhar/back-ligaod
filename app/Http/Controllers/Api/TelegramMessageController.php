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
                'channel_id' => 'required|integer|exists:telegram_parse_channels,id',
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

            // Получаем канал из базы данных
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
}
