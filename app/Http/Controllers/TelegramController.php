<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TelegramChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    /**
     * Получить список каналов/групп
     */
    public function getChannels()
    {
        $channels = TelegramChannel::where('is_active', true)
            ->select(['id', 'title', 'type', 'username', 'description'])
            ->get();

        return response()->json($channels);
    }

    /**
     * Отправить сообщение в телеграм
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|exists:telegram_channels,id',
            'content' => 'required|string',
            'settings' => 'array'
        ]);

        $channel = TelegramChannel::findOrFail($request->channel_id);

        try {
            // Получаем токен бота из конфига
            $botToken = config('services.telegram.bot_token');

            // Формируем URL для API Telegram
            $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

            // Отправляем сообщение
            $response = Http::post($apiUrl, [
                'chat_id' => $channel->chat_id,
                'text' => $request->content,
                'parse_mode' => 'Markdown'
            ]);

            if (!$response->successful()) {
                throw new \Exception('Ошибка при отправке в Telegram: ' . $response->body());
            }

            // Если нужно закрепить сообщение
            if ($request->settings['pinMessage'] ?? false) {
                $messageId = $response->json()['result']['message_id'];

                Http::post("https://api.telegram.org/bot{$botToken}/pinChatMessage", [
                    'chat_id' => $channel->chat_id,
                    'message_id' => $messageId
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Сообщение успешно отправлено'
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка при отправке в Telegram: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке в Telegram'
            ], 500);
        }
    }
}