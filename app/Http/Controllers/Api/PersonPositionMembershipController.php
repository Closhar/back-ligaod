<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonPositionMembership;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonPositionMembershipController extends Controller
{
    /**
     * Получить все членства в должностях для персоны
     */
    public function index(Request $request, Person $person): JsonResponse
    {
        $query = $person->positionMemberships()->with('position');

        // Фильтрация по активности
        if ($request->has('active')) {
            if ($request->boolean('active')) {
                $query->whereNull('ended_at');
            } else {
                $query->whereNotNull('ended_at');
            }
        }

        $memberships = $query->orderBy('started_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $memberships
        ]);
    }

    /**
     * Получить конкретное членство в должности
     */
    public function show(PersonPositionMembership $membership): JsonResponse
    {
        $membership->load(['person', 'position']);

        return response()->json([
            'success' => true,
            'data' => $membership
        ]);
    }

    /**
     * Создать новое членство в должности
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:positions,id',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after:started_at',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Проверяем, нет ли уже активного членства в этой должности
        $existingActive = $person->activePositionMemberships()
            ->where('position_id', $data['position_id'])
            ->exists();

        if ($existingActive && empty($data['ended_at'])) {
            return response()->json([
                'success' => false,
                'message' => 'У персоны уже есть активное членство в этой должности'
            ], 422);
        }

        $membership = $person->positionMemberships()->create($data);
        $membership->load('position');

        return response()->json([
            'success' => true,
            'message' => 'Членство в должности успешно создано',
            'data' => $membership
        ], 201);
    }

    /**
     * Обновить членство в должности
     */
    public function update(Request $request, PersonPositionMembership $membership): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'started_at' => 'sometimes|required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $membership->update($validator->validated());
        $membership->load('position');

        return response()->json([
            'success' => true,
            'message' => 'Членство в должности успешно обновлено',
            'data' => $membership
        ]);
    }

    /**
     * Удалить членство в должности
     */
    public function destroy(PersonPositionMembership $membership): JsonResponse
    {
        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'Членство в должности успешно удалено'
        ]);
    }

    /**
     * Завершить активное членство в должности
     */
    public function endMembership(PersonPositionMembership $membership): JsonResponse
    {
        if ($membership->ended_at) {
            return response()->json([
                'success' => false,
                'message' => 'Членство уже завершено'
            ], 422);
        }

        $membership->update(['ended_at' => now()]);
        $membership->load('position');

        return response()->json([
            'success' => true,
            'message' => 'Членство в должности успешно завершено',
            'data' => $membership
        ]);
    }

    /**
     * Получить активные членства в должностях для персоны
     */
    public function active(Person $person): JsonResponse
    {
        $activeMemberships = $person->activePositionMemberships()
            ->with('position')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activeMemberships
        ]);
    }

    /**
     * Получить историю членств в должностях для персоны
     */
    public function history(Person $person): JsonResponse
    {
        $historicalMemberships = $person->positionMemberships()
            ->whereNotNull('ended_at')
            ->with('position')
            ->orderBy('ended_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $historicalMemberships
        ]);
    }

    /**
     * Получить статистику по членствам в должностях
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => PersonPositionMembership::count(),
            'active' => PersonPositionMembership::whereNull('ended_at')->count(),
            'ended' => PersonPositionMembership::whereNotNull('ended_at')->count(),
            'by_position' => Position::withCount(['positionMemberships', 'activePositionMemberships'])->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
