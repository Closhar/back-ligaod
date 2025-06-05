<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Stream;
use App\Traits\HandlesIframeLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventStreamController extends Controller
{
    use HandlesIframeLinks;

    /**
     * Получить список всех стримов для конкретного события
     */
    public function index(Request $request, Event $event)
    {
        $streams = $event->streams()
            ->orderBy('date', 'desc')
            ->get();

        return response()->json($streams);
    }

    /**
     * Создать новый стрим для конкретного события
     */
    public function store(Request $request, Event $event)
    {
        $data = $this->processIframeInData($request->all());

        \Log::info('Received data:', $data);

        $validator = Validator::make($data, [
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'link' => 'nullable|string|max:1000',
            'in_player' => 'boolean',
            'in_profile' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $link = $validatedData['link'] ?? null;

        \Log::info('Link content:', ['link' => $link]);

        // Проверяем, является ли ссылка embed-ссылкой
        $isEmbedLink = $this->isEmbedLink($link);
        \Log::info('Is embed link:', ['is_embed' => $isEmbedLink]);

        // Если это обычная ссылка, создаем одну запись
        if ($link && !$isEmbedLink) {
            $stream = $event->streams()->create($validatedData);
            return response()->json($stream, 201);
        }

        // Если это embed-ссылка, создаем три записи
        if ($link && $isEmbedLink) {
            $streams = [];

            // Преобразуем URL в зависимости от сервиса
            $convertedUrl = $this->convertEmbedUrl($link);
            \Log::info('Converted URL:', ['converted_url' => $convertedUrl]);

            if ($convertedUrl) {
                // Первая запись (in_player=1, in_profile=0)
                $stream1 = $event->streams()->create([
                    'date' => $validatedData['date'],
                    'title' => $validatedData['title'],
                    'link' => $convertedUrl,
                    'in_player' => true,
                    'in_profile' => false
                ]);
                $streams[] = $stream1;

                // Вторая запись (in_player=0, in_profile=1)
                $stream2 = $event->streams()->create([
                    'date' => $validatedData['date'],
                    'title' => $validatedData['title'],
                    'link' => $convertedUrl,
                    'in_player' => false,
                    'in_profile' => true
                ]);
                $streams[] = $stream2;

                // Третья запись (in_player=0, in_profile=0)
                $stream3 = $event->streams()->create([
                    'date' => $validatedData['date'],
                    'title' => $validatedData['title'],
                    'link' => $convertedUrl,
                    'in_player' => false,
                    'in_profile' => false
                ]);
                $streams[] = $stream3;
            }

            return response()->json($streams, 201);
        }

        return response()->json(['error' => 'Invalid link format'], 422);
    }

    /**
     * Проверяет, является ли ссылка embed-ссылкой
     */
    private function isEmbedLink(?string $url): bool
    {
        if (!$url) return false;

        $embedPatterns = [
            'youtube.com/embed/',
            'vk.com/video_ext.php',
            'vkvideo.ru/video_ext.php',
            'rutube.ru/play/embed/'
        ];

        foreach ($embedPatterns as $pattern) {
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Преобразует embed-ссылку в обычную ссылку
     */
    private function convertEmbedUrl(string $url): string
    {
        // YouTube
        if (str_contains($url, 'youtube.com/embed/')) {
            preg_match('/embed\/([^?]+)/', $url, $matches);
            return $matches[1] ? "https://youtu.be/{$matches[1]}" : $url;
        }

        // VK
        if (str_contains($url, 'vk.com/video_ext.php') || str_contains($url, 'vkvideo.ru/video_ext.php')) {
            preg_match('/oid=([^&]+)&id=([^&]+)/', $url, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $oid = str_replace('-', '', $matches[1]);
                return "https://vk.com/video-{$oid}_{$matches[2]}";
            }
        }

        // Rutube
        if (str_contains($url, 'rutube.ru/play/embed/')) {
            preg_match('/embed\/([^\/]+)/', $url, $matches);
            return $matches[1] ? "https://rutube.ru/video/{$matches[1]}/?r=wd" : $url;
        }

        return $url;
    }

    public function detach(Request $request)
    {
        // Валидация входных данных
        $validator = Validator::make($request->all(), [
            'parent_id' => 'required|integer',
            'related_id' => 'required|integer',
            'parent_model' => 'required|string',
            'relation_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Получаем данные из запроса
        $parentId = $request->input('parent_id');
        $relatedId = $request->input('related_id');
        $parentModelName = $request->input('parent_model');
        $relationName = $request->input('relation_name');

        // Проверяем существование модели
        $parentModelClass = "App\\Models\\{$parentModelName}";
        if (!class_exists($parentModelClass)) {
            return response()->json(['error' => "Model {$parentModelName} not found"], 404);
        }

        // Находим родительскую модель
        $parentModel = $parentModelClass::find($parentId);
        if (!$parentModel) {
            return response()->json(['error' => "Parent model with ID {$parentId} not found"], 404);
        }

        // Проверяем существование связи
        if (!method_exists($parentModel, $relationName)) {
            return response()->json(['error' => "Relation {$relationName} does not exist on {$parentModelName}"], 404);
        }

        try {
            // Получаем тип связи
            $relation = $parentModel->{$relationName}();

            // Обрабатываем связь в зависимости от её типа
            if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\HasMany')) {
                // Для HasMany мы устанавливаем внешний ключ в NULL (если поле допускает NULL)
                $relatedModel = $relation->getRelated()->find($relatedId);
                if (!$relatedModel) {
                    return response()->json(['error' => "Related model with ID {$relatedId} not found"], 404);
                }

                // Получаем имя внешнего ключа
                $foreignKey = $relation->getForeignKeyName();

                // Устанавливаем внешний ключ в NULL
                $relatedModel->{$foreignKey} = null;
                $relatedModel->save();

                return response()->json(['message' => 'Relation detached successfully']);
            }
            elseif (is_a($relation, 'Illuminate\Database\Eloquent\Relations\BelongsToMany')) {
                // Для BelongsToMany используем метод detach()
                $relation->detach($relatedId);

                return response()->json(['message' => 'Relation detached successfully']);
            }
            else {
                return response()->json(['error' => 'Unsupported relation type'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
