<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TelegramChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TelegramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->query('q');
        $perPage = $request->query('per_page', 30);
        $searchId = $request->query('id');
        $fieldParam = $request->query('field');
        $sortField = $request->query('sort_field', 'id');
        $sortDirection = $request->query('sort_direction', 'desc');

        $query = TelegramChannel::query()
            ->select(['id', 'title', 'type', 'username', 'description', 'is_active', 'chat_id']);

        if ($searchId) {
            $query->where('id', $searchId);
        }

        if ($searchQuery) {
            if ($fieldParam) {
                $query->where($fieldParam, 'LIKE', "%{$searchQuery}%");
            } else {
                $query->where('title', 'LIKE', "%{$searchQuery}%");
            }
        }

        $query->orderBy($sortField, $sortDirection);

        $channels = $query->paginate($perPage);
        $total = $channels->total();

        return [
            'current_page' => $channels->currentPage(),
            'data' => $channels->items(),
            'first_page_url' => $channels->url(1),
            'from' => $channels->firstItem(),
            'last_page' => $channels->lastPage(),
            'last_page_url' => $channels->url($channels->lastPage()),
            'links' => $channels->links(),
            'next_page_url' => $channels->nextPageUrl(),
            'path' => $channels->path(),
            'per_page' => $channels->perPage(),
            'prev_page_url' => $channels->previousPageUrl(),
            'to' => $channels->lastItem(),
            'total' => $total,
        ];
    }

    /**
     * Получить список каналов/групп
     */
    public function getChannels()
    {
        $channels = TelegramChannel::where('is_active', true)
            ->select(['id', 'title', 'type', 'username', 'description', 'chat_id'])
            ->get();

        return response()->json($channels);
    }

    /**
     * Отправить сообщение в телеграм
     */
    public function sendMessage(Request $request)
    {
        // Преобразуем данные перед валидацией
        $data = $request->all();

        // Если settings не массив, преобразуем его
        if (isset($data['settings']) && !is_array($data['settings'])) {
            $data['settings'] = json_decode($data['settings'], true) ?? [];
        }

        $validator = \Validator::make($data, [
            'channel_id' => 'required|exists:telegram_channels,id',
            'content' => 'required|string',
            'settings' => 'nullable|array',
            'settings.pinMessage' => 'nullable|boolean',
            'image' => 'nullable|file|image|max:10240', // Максимум 10MB
            'image_url' => 'nullable|url|max:2048' // URL изображения
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        $channel = TelegramChannel::findOrFail($data['channel_id']);

        try {
            // Получаем токен бота из конфига
            $botToken = config('services.telegram.bot_token');
            $envToken = env('TELEGRAM_BOT_TOKEN');

            if (empty($botToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка конфигурации: токен бота не настроен',
                    'details' => [
                        'api_url' => 'https://api.telegram.org/bot[TOKEN]/sendMessage',
                        'token_status' => [
                            'config_token' => $botToken ? 'Присутствует' : 'Отсутствует',
                            'env_token' => $envToken ? 'Присутствует' : 'Отсутствует',
                            'env_value' => $envToken ? (substr($envToken, 0, 5) . '...' . substr($envToken, -5)) : 'Не задан',
                            'config_path' => 'config/services.php',
                            'env_path' => base_path('.env')
                        ]
                    ]
                ], 500);
            }

            $messageId = null;

            // Если есть изображение (файл или URL), отправляем его с текстом
            if ($request->hasFile('image') || !empty($data['image_url'])) {
                $apiUrl = "https://api.telegram.org/bot{$botToken}/sendPhoto";

                // Разделяем текст на части по 1024 символа, обрезая после последней точки
                $text = $data['content'];
                $maxLength = 1024;

                if (mb_strlen($text) > $maxLength) {
                    // Берем первые 1024 символа
                    $tempText = mb_substr($text, 0, $maxLength);
                    // Находим последнюю точку в этой части
                    $lastDot = mb_strrpos($tempText, '.');

                    if ($lastDot !== false) {
                        // Обрезаем по последней точке
                        $caption = mb_substr($text, 0, $lastDot + 1);
                        $remainingText = mb_substr($text, $lastDot + 1);
                    } else {
                        // Если точки нет, обрезаем по 1024 символам
                        $caption = $tempText;
                        $remainingText = mb_substr($text, $maxLength);
                    }
                } else {
                    $caption = $text;
                    $remainingText = '';
                }

                // Подготавливаем параметры запроса
                $params = [
                    'chat_id' => $channel->chat_id,
                    'caption' => $caption,
                    'parse_mode' => 'Markdown'
                ];

                // Если есть файл, отправляем его
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    $response = Http::attach(
                        'photo',
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName(),
                        ['Content-Type' => $file->getMimeType()]
                    )->post($apiUrl, $params);
                }
                // Если есть URL, отправляем по URL
                else if (!empty($data['image_url'])) {
                    $params['photo'] = $data['image_url'];
                    $response = Http::post($apiUrl, $params);
                }

                if (!$response->successful()) {
                    $errorData = $response->json();
                    $errorMessage = isset($errorData['description'])
                        ? $errorData['description']
                        : 'Неизвестная ошибка при отправке в Telegram';

                    // Маскируем часть токена для безопасности
                    $maskedToken = substr($botToken, 0, 5) . '...' . substr($botToken, -5);
                    $maskedUrl = str_replace($botToken, $maskedToken, $apiUrl);

                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'details' => [
                            'status' => $response->status(),
                            'response' => $errorData,
                            'chat_id' => $channel->chat_id,
                            'api_url' => $maskedUrl,
                            'token_length' => strlen($botToken),
                            'token_status' => [
                                'config_token' => $botToken ? 'Присутствует' : 'Отсутствует',
                                'env_token' => $envToken ? 'Присутствует' : 'Отсутствует'
                            ]
                        ]
                    ], 500);
                }

                $messageId = $response->json()['result']['message_id'];

                // Если остался текст, отправляем его отдельным сообщением
                if (!empty($remainingText)) {
                    $textApiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
                    $textResponse = Http::post($textApiUrl, [
                        'chat_id' => $channel->chat_id,
                        'text' => $remainingText,
                        'parse_mode' => 'Markdown'
                    ]);

                    if (!$textResponse->successful()) {
                        $errorData = $textResponse->json();
                        $errorMessage = isset($errorData['description'])
                            ? $errorData['description']
                            : 'Неизвестная ошибка при отправке текста в Telegram';

                        // Маскируем часть токена для безопасности
                        $maskedToken = substr($botToken, 0, 5) . '...' . substr($botToken, -5);
                        $maskedUrl = str_replace($botToken, $maskedToken, $textApiUrl);

                        return response()->json([
                            'success' => false,
                            'message' => $errorMessage,
                            'details' => [
                                'status' => $textResponse->status(),
                                'response' => $errorData,
                                'chat_id' => $channel->chat_id,
                                'api_url' => $maskedUrl,
                                'token_length' => strlen($botToken),
                                'token_status' => [
                                    'config_token' => $botToken ? 'Присутствует' : 'Отсутствует',
                                    'env_token' => $envToken ? 'Присутствует' : 'Отсутствует'
                                ]
                            ]
                        ], 500);
                    }
                }
            } else {
                // Отправляем только текст
                $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
                $response = Http::post($apiUrl, [
                    'chat_id' => $channel->chat_id,
                    'text' => $data['content'],
                    'parse_mode' => 'Markdown'
                ]);

                if (!$response->successful()) {
                    $errorData = $response->json();
                    $errorMessage = isset($errorData['description'])
                        ? $errorData['description']
                        : 'Неизвестная ошибка при отправке в Telegram';

                    // Маскируем часть токена для безопасности
                    $maskedToken = substr($botToken, 0, 5) . '...' . substr($botToken, -5);
                    $maskedUrl = str_replace($botToken, $maskedToken, $apiUrl);

                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'details' => [
                            'status' => $response->status(),
                            'response' => $errorData,
                            'chat_id' => $channel->chat_id,
                            'api_url' => $maskedUrl,
                            'token_length' => strlen($botToken),
                            'token_status' => [
                                'config_token' => $botToken ? 'Присутствует' : 'Отсутствует',
                                'env_token' => $envToken ? 'Присутствует' : 'Отсутствует'
                            ]
                        ]
                    ], 500);
                }

                $messageId = $response->json()['result']['message_id'];
            }

            // Если нужно закрепить сообщение
            if (!empty($data['settings']['pinMessage']) && $messageId) {
                $pinApiUrl = "https://api.telegram.org/bot{$botToken}/pinChatMessage";

                $pinResponse = Http::post($pinApiUrl, [
                    'chat_id' => $channel->chat_id,
                    'message_id' => $messageId
                ]);

                if (!$pinResponse->successful()) {
                    $pinErrorData = $pinResponse->json();
                    // Маскируем часть токена для безопасности
                    $maskedToken = substr($botToken, 0, 5) . '...' . substr($botToken, -5);
                    $maskedPinUrl = str_replace($botToken, $maskedToken, $pinApiUrl);

                    return response()->json([
                        'success' => false,
                        'message' => 'Сообщение отправлено, но не удалось закрепить',
                        'details' => [
                            'status' => $pinResponse->status(),
                            'response' => $pinErrorData,
                            'api_url' => $maskedPinUrl,
                            'token_length' => strlen($botToken),
                            'token_status' => [
                                'config_token' => $botToken ? 'Присутствует' : 'Отсутствует',
                                'env_token' => $envToken ? 'Присутствует' : 'Отсутствует'
                            ]
                        ]
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Сообщение успешно отправлено'
            ]);

        } catch (\Exception $e) {
            // Маскируем часть токена для безопасности
            $maskedToken = isset($botToken) ? (substr($botToken, 0, 5) . '...' . substr($botToken, -5)) : '[TOKEN]';
            $maskedUrl = isset($apiUrl) ? str_replace($botToken, $maskedToken, $apiUrl) : 'https://api.telegram.org/bot[TOKEN]/sendMessage';

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке в Telegram',
                'details' => [
                    'error' => $e->getMessage(),
                    'chat_id' => $channel->chat_id ?? null,
                    'api_url' => $maskedUrl,
                    'token_status' => [
                        'config_token' => isset($botToken) ? 'Присутствует' : 'Отсутствует',
                        'env_token' => isset($envToken) ? 'Присутствует' : 'Отсутствует',
                        'env_value' => isset($envToken) ? (substr($envToken, 0, 5) . '...' . substr($envToken, -5)) : 'Не задан'
                    ]
                ]
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:channel,group',
                'username' => 'required|string|max:255|unique:telegram_channels',
                'chat_id' => 'required|string|max:255|unique:telegram_channels',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            $channel = TelegramChannel::create($validated);

            return response()->json($channel, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить канал/группу
     */
    public function update(Request $request, $id)
    {
        $channel = TelegramChannel::findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'type' => 'string|in:channel,group',
            'username' => 'string|max:255|unique:telegram_channels,username,' . $id,
            'chat_id' => 'string|max:255|unique:telegram_channels,chat_id,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $channel->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $channel
        ]);
    }
}