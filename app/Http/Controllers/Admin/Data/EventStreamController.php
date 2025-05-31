<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventStreamController extends Controller
{
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
        $data = $request->all();

        // Проверяем, является ли link iframe-кодом
        if (isset($data['link']) && strpos($data['link'], '<iframe') !== false) {
            // Извлекаем URL из атрибута src
            if (preg_match('/src="([^"]+)"/', $data['link'], $matches)) {
                $data['link'] = $matches[1];
            }
        }

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

        $stream = $event->streams()->create($validator->validated());

        return response()->json($stream, 201);
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
