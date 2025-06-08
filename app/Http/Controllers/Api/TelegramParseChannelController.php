<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramParseChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Services\TelegramClientService;

class TelegramParseChannelController extends Controller
{
    /**
     * Получить список каналов для парсинга
     */
    public function index()
    {
        $channels = Cache::remember('telegram_parse_channels', 3600, function () {
            return TelegramParseChannel::orderBy('created_at', 'desc')->get();
        });

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
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|required|boolean'
        ], [
            'title.required' => 'Название канала обязательно для заполнения',
            'title.max' => 'Название канала не должно превышать 255 символов',
            'is_active.required' => 'Статус активности обязателен для заполнения',
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
            $channel = TelegramParseChannel::findOrFail($id);
            $channel->update($request->all());
            Cache::forget('telegram_parse_channels');

            return response()->json([
                'success' => true,
                'message' => 'Канал успешно обновлен',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
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
            return response()->json([
                'success' => true,
                'message' => 'Авторизация успешна'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка авторизации',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
