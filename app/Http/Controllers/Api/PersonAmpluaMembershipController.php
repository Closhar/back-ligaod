<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amplua;
use App\Models\Person;
use App\Models\PersonAmpluaMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonAmpluaMembershipController extends Controller
{
    /**
     * Получить все членства в амплуа для персоны
     */
    public function index(Request $request, Person $person): JsonResponse
    {
        $query = $person->ampluaMemberships()->with('amplua');

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
     * Получить конкретное членство в амплуа
     */
    public function show(Request $request, Person $person, $membershipId): JsonResponse
    {
        $membership = PersonAmpluaMembership::where('id', $membershipId)
            ->where('person_id', $person->id)
            ->with(['person', 'amplua'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $membership
        ]);
    }

    /**
     * Создать новое членство в амплуа
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amplua_id' => 'required|exists:ampluas,id',
            'started_at' => 'nullable|date_format:Y-m-d',
            'ended_at' => 'nullable|date_format:Y-m-d|after:started_at',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (empty($data['started_at'])) $data['started_at'] = null;
        if (empty($data['ended_at'])) $data['ended_at'] = null;
        if (empty($data['notes'])) $data['notes'] = null;

        // Проверяем, нет ли уже активного членства в этом амплуа
        $existingActive = $person->activeAmpluaMemberships()
            ->where('amplua_id', $data['amplua_id'])
            ->exists();

        if ($existingActive && empty($data['ended_at'])) {
            return response()->json([
                'success' => false,
                'message' => 'У персоны уже есть активное членство в этом амплуа'
            ], 422);
        }

        $membership = $person->ampluaMemberships()->create($data);
        $membership->load('amplua');

        return response()->json([
            'success' => true,
            'message' => 'Членство в амплуа успешно создано',
            'data' => $membership
        ], 201);
    }

    /**
     * Обновить членство в амплуа
     */
    public function update(Request $request, Person $person, $membershipId): JsonResponse
    {
        $membership = PersonAmpluaMembership::where('id', $membershipId)
            ->where('person_id', $person->id)
            ->firstOrFail();

        // Получаем и обрабатываем данные
        $data = $request->all();
        if (empty($data['started_at'])) $data['started_at'] = null;
        if (empty($data['ended_at'])) $data['ended_at'] = null;
        if (empty($data['notes'])) $data['notes'] = null;

        $validator = Validator::make($data, [
            'amplua_id' => 'sometimes|required|exists:ampluas,id',
            'started_at' => 'nullable|date_format:Y-m-d',
            'ended_at' => 'nullable|date_format:Y-m-d|after:started_at',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $membership->update($validatedData);
        $membership->load('amplua');

        return response()->json([
            'success' => true,
            'message' => 'Членство в амплуа успешно обновлено',
            'data' => $membership
        ]);
    }

    /**
     * Удалить членство в амплуа
     */
    public function destroy(Request $request, Person $person, $membershipId): JsonResponse
    {
        $membership = PersonAmpluaMembership::where('id', $membershipId)
            ->where('person_id', $person->id)
            ->firstOrFail();

        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'Членство в амплуа успешно удалено'
        ]);
    }

    /**
     * Завершить активное членство в амплуа
     */
    public function endMembership(Request $request, Person $person, $membershipId): JsonResponse
    {
        $membership = PersonAmpluaMembership::where('id', $membershipId)
            ->where('person_id', $person->id)
            ->firstOrFail();

        if ($membership->ended_at) {
            return response()->json([
                'success' => false,
                'message' => 'Членство уже завершено'
            ], 422);
        }

        $membership->update(['ended_at' => now()]);
        $membership->load('amplua');

        return response()->json([
            'success' => true,
            'message' => 'Членство в амплуа успешно завершено',
            'data' => $membership
        ]);
    }

    /**
     * Получить активные членства в амплуа для персоны
     */
    public function active(Person $person): JsonResponse
    {
        $activeMemberships = $person->activeAmpluaMemberships()
            ->with('amplua')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activeMemberships
        ]);
    }

    /**
     * Получить историю членств в амплуа для персоны
     */
    public function history(Person $person): JsonResponse
    {
        $historicalMemberships = $person->ampluaMemberships()
            ->whereNotNull('ended_at')
            ->with('amplua')
            ->orderBy('ended_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $historicalMemberships
        ]);
    }

    /**
     * Получить статистику по членствам в амплуа
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => PersonAmpluaMembership::count(),
            'active' => PersonAmpluaMembership::whereNull('ended_at')->count(),
            'ended' => PersonAmpluaMembership::whereNotNull('ended_at')->count(),
            'by_amplua' => Amplua::withCount(['ampluaMemberships', 'activeAmpluaMemberships'])->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
