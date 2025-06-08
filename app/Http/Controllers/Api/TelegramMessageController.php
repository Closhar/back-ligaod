<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramChannel;
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
     * Получение сообщений из канала
     */
    public function fetchMessages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel_id' => 'required|exists:telegram_channels,id',
            'date_from' => 'required|date',
            'limit' => 'nullable|integer|min:1|max:100'
        ], [
            'channel_id.required' => 'ID канала обязателен для заполнения',
            'channel_id.exists' => 'Канал с указанным ID не найден',
            'date_from.required' => 'Дата начала обязательна для заполнения',
            'date_from.date' => 'Неверный формат даты',
            'limit.integer' => 'Лимит должен быть целым числом',
            'limit.min' => 'Лимит должен быть не менее 1',
            'limit.max' => 'Лимит не может быть более 100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => collect($validator->errors())->map(function ($errors) {
                    return $errors[0];
                })->toArray()
            ], 422);
        }

        try {
            $channel = TelegramChannel::findOrFail($request->channel_id);

            // Получаем сообщения через Telegram Client API
            $messages = $this->telegramClientService->getChannelMessages(
                $channel->username ?? $channel->chat_id,
                $request->date_from,
                $request->input('limit', 100)
            );

            // Сохраняем сообщения в базу данных
            $savedMessages = [];
            foreach ($messages as $message) {
                $savedMessage = TelegramMessage::updateOrCreate(
                    [
                        'channel_id' => $channel->id,
                        'message_id' => $message['message_id']
                    ],
                    [
                        'content' => $message['text'] ?? null,
                        'media' => $message['media'] ?? null,
                        'message_date' => $message['date'],
                        'raw_data' => $message['raw_data'] ?? $message
                    ]
                );
                $savedMessages[] = $savedMessage;
            }

            // Обновляем время последнего парсинга
            $channel->update(['last_parsed_at' => now()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $savedMessages,
                    'total' => count($savedMessages),
                    'channel' => [
                        'id' => $channel->id,
                        'title' => $channel->title,
                        'username' => $channel->username
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении сообщений: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении сообщений',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
