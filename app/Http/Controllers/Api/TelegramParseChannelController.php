<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramParseChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $channel = TelegramParseChannel::create($request->all());
            Cache::forget('telegram_parse_channels');

            return response()->json([
                'success' => true,
                'message' => 'Channel added successfully',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add channel',
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $channel = TelegramParseChannel::findOrFail($id);
            $channel->update($request->all());
            Cache::forget('telegram_parse_channels');

            return response()->json([
                'success' => true,
                'message' => 'Channel updated successfully',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update channel',
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
                'message' => 'Channel deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete channel',
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
                'message' => 'Failed to get channel stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
