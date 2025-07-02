<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonSurnameChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonSurnameChangeController extends Controller
{
    /**
     * Получить все смены фамилий персоны
     */
    public function index(Person $person): JsonResponse
    {
        $surnameChanges = $person->surnameChanges()
            ->orderBy('valid_until', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $surnameChanges
        ]);
    }

    /**
     * Добавить смену фамилии
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'old_surname' => 'required|string|max:255',
            'valid_until' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Проверяем, что старая фамилия отличается от текущей
        if ($request->old_surname === $person->last_name) {
            return response()->json([
                'success' => false,
                'message' => 'Старая фамилия не может совпадать с текущей'
            ], 422);
        }

        $surnameChange = PersonSurnameChange::create([
            'person_id' => $person->id,
            'old_surname' => $request->old_surname,
            'valid_until' => $request->valid_until,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Смена фамилии успешно добавлена',
            'data' => $surnameChange
        ], 201);
    }

    /**
     * Обновить смену фамилии
     */
    public function update(Request $request, Person $person, PersonSurnameChange $surnameChange): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'old_surname' => 'sometimes|required|string|max:255',
            'valid_until' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Проверяем, что старая фамилия отличается от текущей
        if ($request->has('old_surname') && $request->old_surname === $person->last_name) {
            return response()->json([
                'success' => false,
                'message' => 'Старая фамилия не может совпадать с текущей'
            ], 422);
        }

        $surnameChange->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Смена фамилии успешно обновлена',
            'data' => $surnameChange
        ]);
    }

    /**
     * Удалить смену фамилии
     */
    public function destroy(Person $person, PersonSurnameChange $surnameChange): JsonResponse
    {
        $surnameChange->delete();

        return response()->json([
            'success' => true,
            'message' => 'Смена фамилии успешно удалена'
        ]);
    }

    /**
     * Получить актуальные смены фамилий
     */
    public function valid(Person $person): JsonResponse
    {
        $surnameChanges = $person->surnameChanges()
            ->valid()
            ->orderBy('valid_until', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $surnameChanges
        ]);
    }

    /**
     * Получить исторические смены фамилий
     */
    public function historical(Person $person): JsonResponse
    {
        $surnameChanges = $person->surnameChanges()
            ->historical()
            ->orderBy('valid_until', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $surnameChanges
        ]);
    }
}
