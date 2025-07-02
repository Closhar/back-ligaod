<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonRoleMembership;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonRoleMembershipController extends Controller
{
    /**
     * Получить все членства в ролях для персоны
     */
    public function index(Request $request, Person $person): JsonResponse
    {
        $query = $person->roleMemberships()->with('role');

        // Фильтрация по активности
        if ($request->has('active')) {
            if ($request->boolean('active')) {
                $query->whereNull('ended_at');
            } else {
                $query->whereNotNull('ended_at');
            }
        }

        // Фильтрация по типу роли
        if ($request->has('role_type')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('type', $request->role_type);
            });
        }

        $memberships = $query->orderBy('started_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $memberships
        ]);
    }

    /**
     * Получить конкретное членство в роли
     */
    public function show(PersonRoleMembership $membership): JsonResponse
    {
        $membership->load(['person', 'role']);

        return response()->json([
            'success' => true,
            'data' => $membership
        ]);
    }

    /**
     * Создать новое членство в роли
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
            'started_at' => 'required|date',
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

        // Проверяем, нет ли уже активного членства в этой роли
        $existingActive = $person->activeRoleMemberships()
            ->where('role_id', $data['role_id'])
            ->exists();

        if ($existingActive && empty($data['ended_at'])) {
            return response()->json([
                'success' => false,
                'message' => 'У персоны уже есть активное членство в этой роли'
            ], 422);
        }

        $membership = $person->roleMemberships()->create($data);
        $membership->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Членство в роли успешно создано',
            'data' => $membership
        ], 201);
    }

    /**
     * Обновить членство в роли
     */
    public function update(Request $request, PersonRoleMembership $membership): JsonResponse
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
        $membership->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Членство в роли успешно обновлено',
            'data' => $membership
        ]);
    }

    /**
     * Удалить членство в роли
     */
    public function destroy(PersonRoleMembership $membership): JsonResponse
    {
        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'Членство в роли успешно удалено'
        ]);
    }

    /**
     * Завершить активное членство в роли
     */
    public function endMembership(PersonRoleMembership $membership): JsonResponse
    {
        if ($membership->ended_at) {
            return response()->json([
                'success' => false,
                'message' => 'Членство уже завершено'
            ], 422);
        }

        $membership->update(['ended_at' => now()]);
        $membership->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Членство в роли успешно завершено',
            'data' => $membership
        ]);
    }

    /**
     * Получить активные членства в ролях для персоны
     */
    public function active(Person $person): JsonResponse
    {
        $activeMemberships = $person->activeRoleMemberships()
            ->with('role')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activeMemberships
        ]);
    }

    /**
     * Получить историю членств в ролях для персоны
     */
    public function history(Person $person): JsonResponse
    {
        $historicalMemberships = $person->roleMemberships()
            ->whereNotNull('ended_at')
            ->with('role')
            ->orderBy('ended_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $historicalMemberships
        ]);
    }

    /**
     * Получить статистику по членствам в ролях
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => PersonRoleMembership::count(),
            'active' => PersonRoleMembership::active()->count(),
            'historical' => PersonRoleMembership::historical()->count(),
            'sportsman' => PersonRoleMembership::sportsman()->count(),
            'non_sportsman' => PersonRoleMembership::nonSportsman()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
