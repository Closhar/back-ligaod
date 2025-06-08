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
     * Получение сообщений из канала
     */
    public function fetchMessages(Request $request)
    {
        \Log::info('Получен запрос на получение сообщений:', [
            'all' => $request->all(),
            'query' => $request->query(),
            'headers' => $request->headers->all()
        ]);

        $validator = Validator::make($request->all(), [
            'channel_id' => 'required|exists:telegram_parse_channels,id',
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
            \Log::error('Ошибка валидации:', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => collect($validator->errors())->map(function ($errors) {
                    return $errors[0];
                })->toArray()
            ], 422);
        }

        try {
            $channel = TelegramParseChannel::findOrFail($request->channel_id);
            \Log::info('Найден канал:', $channel->toArray());

            // Получаем сообщения через Telegram Client API
            $messages = $this->telegramClientService->getChannelMessages(
                $channel->id,
                $request->date_from,
                $request->input('limit', 100)
            );

            \Log::info('Получены сообщения:', ['count' => count($messages['messages'])]);

            // Сохраняем сообщения в базу данных
            $savedMessages = [];
            foreach ($messages['messages'] as $message) {
                $savedMessage = TelegramMessage::updateOrCreate(
                    [
                        'channel_id' => $channel->id,
                        'message_id' => $message['id']
                    ],
                    [
                        'content' => $message['message'] ?? null,
                        'media' => $message['media'] ?? null,
                        'message_date' => date('Y-m-d H:i:s', $message['date']),
                        'raw_data' => $message
                    ]
                );
                $savedMessages[] = $savedMessage;
            }

            // Обновляем время последнего парсинга
            $channel->update(['last_parse_at' => now()]);

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
            \Log::error('Ошибка при получении сообщений: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении сообщений',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
