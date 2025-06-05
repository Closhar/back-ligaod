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

        $validator = Validator::make($data, [
            'date' => 'required|date',
            'title' => 'required|string|max:255',
            'link' => 'nullable|url|max:500',
            'in_player' => 'boolean',
            'in_profile' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $link = $validatedData['link'] ?? null;

        // Если это обычная ссылка, создаем одну запись
        if ($link && !str_contains($link, '<iframe')) {
            $stream = $event->streams()->create($validatedData);
            return response()->json($stream, 201);
        }

        // Если это iframe, создаем три записи
        if ($link && str_contains($link, '<iframe')) {
            $streams = [];

            // Извлекаем URL из iframe
            preg_match('/src="([^"]+)"/', $link, $matches);
            $iframeUrl = $matches[1] ?? null;

            if ($iframeUrl) {
                // Преобразуем URL в зависимости от сервиса
                $convertedUrl = $this->convertIframeUrl($iframeUrl);

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
     * Преобразует URL из iframe в обычную ссылку
     */
    private function convertIframeUrl(string $iframeUrl): string
    {
        // YouTube
        if (str_contains($iframeUrl, 'youtube.com/embed/')) {
            preg_match('/embed\/([^?]+)/', $iframeUrl, $matches);
            return $matches[1] ? "https://youtu.be/{$matches[1]}" : $iframeUrl;
        }

        // VK
        if (str_contains($iframeUrl, 'vk.com/video_ext.php') || str_contains($iframeUrl, 'vkvideo.ru/video_ext.php')) {
            preg_match('/oid=([^&]+)&id=([^&]+)/', $iframeUrl, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $oid = str_replace('-', '', $matches[1]);
                return "https://vk.com/video-{$oid}_{$matches[2]}";
            }
        }

        // Rutube
        if (str_contains($iframeUrl, 'rutube.ru/play/embed/')) {
            preg_match('/embed\/([^\/]+)/', $iframeUrl, $matches);
            return $matches[1] ? "https://rutube.ru/video/{$matches[1]}/?r=wd" : $iframeUrl;
        }

        return $iframeUrl;
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
