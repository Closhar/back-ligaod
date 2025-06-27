<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ApiRelationsController extends Controller
{
    /**
     * Сохранение morphedByMany отношений
     */
    public function saveRelations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'relation_name' => 'required|string',
            'related_ids' => 'array',
            'related_ids.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $modelType = $request->input('model_type');
            $modelId = $request->input('model_id');
            $relationName = $request->input('relation_name');
            $relatedIds = $request->input('related_ids');

            // Определяем модель на основе типа
            $modelClass = $this->getModelClass($modelType);
            if (!$modelClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неизвестный тип модели'
                ], 400);
            }

            // Находим основную модель
            $model = $modelClass::find($modelId);
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Модель не найдена'
                ], 404);
            }

            // Сохраняем отношения в транзакции
            DB::transaction(function () use ($model, $relationName, $relatedIds) {
                // Удаляем все существующие отношения
                $model->$relationName()->detach();

                // Добавляем новые отношения
                if (!empty($relatedIds)) {
                    $model->$relationName()->attach($relatedIds);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Отношения успешно сохранены',
                'data' => [
                    'model_type' => $modelType,
                    'model_id' => $modelId,
                    'relation_name' => $relationName,
                    'related_ids' => $relatedIds
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при сохранении отношений: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение списка связанных записей
     */
    public function getRelations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'relation_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $modelType = $request->input('model_type');
            $modelId = $request->input('model_id');
            $relationName = $request->input('relation_name');

            // Определяем модель на основе типа
            $modelClass = $this->getModelClass($modelType);
            if (!$modelClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неизвестный тип модели'
                ], 400);
            }

            // Находим основную модель
            $model = $modelClass::find($modelId);
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Модель не найдена'
                ], 404);
            }

            // Получаем связанные записи
            $relations = $model->$relationName()->get();

            return response()->json([
                'success' => true,
                'data' => $relations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении отношений: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение списка доступных записей для связи
     */
    public function getAvailableRecords(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'related_model_type' => 'required|string',
            'search' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $relatedModelType = $request->input('related_model_type');
            $search = $request->input('search');

            // Определяем модель на основе типа
            $modelClass = $this->getModelClass($relatedModelType);
            if (!$modelClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неизвестный тип модели'
                ], 400);
            }

            // Получаем записи с поиском
            $query = $modelClass::query();

            if ($search) {
                // Ищем по названию или имени (адаптируйте под ваши поля)
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%");
                });
            }

            $records = $query->limit(50)->get();

            return response()->json([
                'success' => true,
                'data' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении записей: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Определение класса модели по типу
     */
    private function getModelClass(string $modelType): ?string
    {
        $modelMap = [
            'arena' => \App\Models\Arena::class,
            'article' => \App\Models\Article::class,
            'sport' => \App\Models\Sport::class,
            'city' => \App\Models\City::class,
            // Добавьте другие модели по необходимости
        ];

        return $modelMap[$modelType] ?? null;
    }
}