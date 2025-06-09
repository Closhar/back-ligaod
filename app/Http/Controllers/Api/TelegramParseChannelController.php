<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramParseChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Services\TelegramClientService;
use Illuminate\Support\Facades\Log;

class TelegramParseChannelController extends Controller
{
    /**
     * Получить список каналов для парсинга
     */
    public function index(Request $request)
    {
        $query = TelegramParseChannel::query();

        if ($request->has('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('username', 'like', '%' . $searchTerm . '%')
                  ->orWhere('channel_id', 'like', '%' . $searchTerm . '%');
            });
        }

        $channels = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $channels
        ]);
    }

    /**
     * Добавить новый канал для парсинга
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel_id' => 'required|string|unique:telegram_parse_channels',
            'username' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'is_active' => 'boolean'
        ], [
            'channel_id.required' => 'ID канала обязателен для заполнения',
            'channel_id.unique' => 'Канал с таким ID уже существует',
            'title.required' => 'Название канала обязательно для заполнения',
            'title.max' => 'Название канала не должно превышать 255 символов',
            'username.max' => 'Имя пользователя не должно превышать 255 символов',
            'is_active.boolean' => 'Статус активности должен быть true или false'
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
            $channel = TelegramParseChannel::create($request->all());
            Cache::forget('telegram_parse_channels');

            return response()->json([
                'success' => true,
                'message' => 'Канал успешно добавлен',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось добавить канал',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить канал парсинга
     */
    public function update(Request $request, $id)
    {
        \Log::info('Получены данные для обновления:', $request->all());

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|required|boolean'
        ], [
            'title.required' => 'Название канала обязательно для заполнения',
            'title.max' => 'Название канала не должно превышать 255 символов',
            'username.max' => 'Имя пользователя не должно превышать 255 символов',
            'is_active.required' => 'Статус активности обязателен для заполнения',
            'is_active.boolean' => 'Статус активности должен быть true или false'
        ]);

        if ($validator->fails()) {
            \Log::error('Ошибка валидации:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => collect($validator->errors())->map(function ($errors) {
                    return $errors[0];
                })->toArray()
            ], 422);
        }

        try {
            $channel = TelegramParseChannel::findOrFail($id);
            \Log::info('Текущие данные канала:', $channel->toArray());

            $channel->update($request->all());
            \Log::info('Обновленные данные канала:', $channel->fresh()->toArray());

            Cache::forget('telegram_parse_channels');

            return response()->json([
                'success' => true,
                'message' => 'Канал успешно обновлен',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка обновления канала: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить канал',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить канал парсинга
     */
    public function destroy($id)
    {
        try {
            $channel = TelegramParseChannel::findOrFail($id);
            $channel->delete();
            Cache::forget('telegram_parse_channels');

            return response()->json([
                'success' => true,
                'message' => 'Канал успешно удален'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось удалить канал',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику по каналу
     */
    public function stats($id)
    {
        try {
            $channel = TelegramParseChannel::findOrFail($id);
            $stats = Cache::remember("telegram_channel_stats_{$id}", 300, function () use ($channel) {
                return [
                    'messages_count' => $channel->messages_count,
                    'last_parse_at' => $channel->last_parse_at,
                    'parse_status' => $channel->parse_status,
                    'error_message' => $channel->error_message
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить статистику канала',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Тестирование авторизации в Telegram
     */
    public function testAuth()
    {
        try {
            $telegramService = app(TelegramClientService::class);

            // Пробуем получить информацию о текущем пользователе
            $self = $telegramService->getSelf();

            return response()->json([
                'success' => true,
                'message' => 'Авторизация успешна',
                'data' => [
                    'user' => $self
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка авторизации: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка авторизации',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Проверка информации о канале
     */
    public function checkChannel($id)
    {
        try {
            $channel = TelegramParseChannel::findOrFail($id);
            $telegramService = app(TelegramClientService::class);

            // Получаем информацию о канале
            $channelInfo = $telegramService->getChannelInfo($channel->channel_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => [
                        'id' => $channel->id,
                        'title' => $channel->title,
                        'username' => $channel->username,
                        'channel_id' => $channel->channel_id
                    ],
                    'telegram_info' => $channelInfo
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке канала',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить информацию о канале
     */
    public function show($id)
    {
        try {
            $channel = TelegramParseChannel::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить информацию о канале',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Тестирование получения сообщений из канала
     */
    public function testMessages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'channel_id' => 'required|string',
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

            \Log::info('Начало тестирования получения сообщений', [
                'channel_id' => $request->channel_id,
                'limit' => $request->limit,
                'offset' => $request->offset,
                'date_from' => $request->date_from
            ]);

            $telegramService = app(TelegramClientService::class);

            // Сначала проверим авторизацию
            try {
                $self = $telegramService->getSelf();
                \Log::info('Информация о текущем пользователе:', ['self' => $self]);
            } catch (\Exception $e) {
                \Log::error('Ошибка при получении информации о пользователе: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка авторизации в Telegram',
                    'error' => $e->getMessage()
                ], 500);
            }

            // Получаем информацию о канале
            try {
                $channelInfo = $telegramService->getChannelInfo($request->channel_id);
                \Log::info('Информация о канале:', ['info' => $channelInfo]);
            } catch (\Exception $e) {
                \Log::error('Ошибка при получении информации о канале: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении информации о канале',
                    'error' => $e->getMessage()
                ], 500);
            }

            // Пробуем получить сообщения
            try {
                $messages = $telegramService->getChannelMessages(
                    $request->channel_id,
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

            return response()->json([
                'success' => true,
                'data' => [
                    'channel_info' => $channelInfo,
                    'messages' => $messages['messages'],
                    'pagination' => [
                        'has_more' => $messages['has_more'],
                        'next_offset' => $messages['next_offset']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Общая ошибка при тестировании сообщений: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при тестировании сообщений',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Проверка доступа к каналу
     */
    public function checkAccess(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'channel_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $telegramService = app(TelegramClientService::class);
            $result = $telegramService->checkChannelAccess($request->channel_id);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при проверке доступа к каналу: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке доступа к каналу',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
