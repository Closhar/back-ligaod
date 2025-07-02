<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonSportMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonSportMembershipController extends Controller
{
    /**
     * Получить все членства персоны в видах спорта
     */
    public function index(Person $person): JsonResponse
    {
        $memberships = $person->sportMemberships()
            ->with('sport')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $memberships
        ]);
    }

    /**
     * Добавить персону в вид спорта
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sport_id' => 'required|exists:sports,id',
            'started_at' => 'required|date|before_or_equal:today',
            'ended_at' => 'nullable|date|after:started_at',
            'level' => 'nullable|string|max:255',
            'achievements' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Проверяем, нет ли уже активного членства в этом виде спорта
        $existingMembership = $person->sportMemberships()
            ->where('sport_id', $request->sport_id)
            ->whereNull('ended_at')
            ->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Персона уже занимается этим видом спорта'
            ], 422);
        }

        $membership = PersonSportMembership::create([
            'person_id' => $person->id,
            'sport_id' => $request->sport_id,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at,
            'level' => $request->level,
            'achievements' => $request->achievements,
        ]);

        $membership->load('sport');

        return response()->json([
            'success' => true,
            'message' => 'Членство в виде спорта успешно добавлено',
            'data' => $membership
        ], 201);
    }

    /**
     * Обновить членство в виде спорта
     */
    public function update(Request $request, Person $person, PersonSportMembership $membership): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'started_at' => 'sometimes|required|date|before_or_equal:today',
            'ended_at' => 'nullable|date|after:started_at',
            'level' => 'nullable|string|max:255',
            'achievements' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $membership->update($validator->validated());
        $membership->load('sport');

        return response()->json([
            'success' => true,
            'message' => 'Членство в виде спорта успешно обновлено',
            'data' => $membership
        ]);
    }

    /**
     * Удалить членство в виде спорта
     */
    public function destroy(Person $person, PersonSportMembership $membership): JsonResponse
    {
        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'Членство в виде спорта успешно удалено'
        ]);
    }

    /**
     * Завершить активное членство в виде спорта
     */
    public function end(Request $request, Person $person, PersonSportMembership $membership): JsonResponse
    {
        if ($membership->ended_at) {
            return response()->json([
                'success' => false,
                'message' => 'Членство уже завершено'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'ended_at' => 'required|date|after:started_at|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $membership->update(['ended_at' => $request->ended_at]);
        $membership->load('sport');

        return response()->json([
            'success' => true,
            'message' => 'Членство в виде спорта успешно завершено',
            'data' => $membership
        ]);
    }

    /**
     * Получить активные членства персоны в видах спорта
     */
    public function active(Person $person): JsonResponse
    {
        $memberships = $person->activeSportMemberships()
            ->with('sport')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $memberships
        ]);
    }
}
