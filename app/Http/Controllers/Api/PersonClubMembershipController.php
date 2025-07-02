<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonClubMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonClubMembershipController extends Controller
{
    /**
     * Получить все членства персоны в клубах
     */
    public function index(Person $person): JsonResponse
    {
        $memberships = $person->clubMemberships()
            ->with('club')
            ->orderBy('joined_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $memberships
        ]);
    }

    /**
     * Добавить персону в клуб
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'club_id' => 'required|exists:clubs,id',
            'joined_at' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'left_at' => 'nullable|date_format:Y-m-d|after:joined_at',
            'position' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Проверяем, нет ли уже активного членства в этом клубе
        $existingMembership = $person->clubMemberships()
            ->where('club_id', $request->club_id)
            ->whereNull('left_at')
            ->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Персона уже является членом этого клуба'
            ], 422);
        }

        $data = $validator->validated();
        if (empty($data['joined_at'])) $data['joined_at'] = null;
        if (empty($data['left_at'])) $data['left_at'] = null;
        if (empty($data['position'])) $data['position'] = null;
        if (empty($data['notes'])) $data['notes'] = null;

        $membership = PersonClubMembership::create([
            'person_id' => $person->id,
            'club_id' => $data['club_id'],
            'joined_at' => $data['joined_at'],
            'left_at' => $data['left_at'],
            'position' => $data['position'],
            'notes' => $data['notes'],
        ]);

        $membership->load('club');

        return response()->json([
            'success' => true,
            'message' => 'Членство в клубе успешно добавлено',
            'data' => $membership
        ], 201);
    }

    /**
     * Обновить членство в клубе
     */
    public function update(Request $request, Person $person, $membershipId): JsonResponse
    {
        $membership = PersonClubMembership::where('id', $membershipId)
            ->where('person_id', $person->id)
            ->firstOrFail();

        // Получаем и обрабатываем данные
        $data = $request->all();
        if (empty($data['joined_at'])) $data['joined_at'] = null;
        if (empty($data['left_at'])) $data['left_at'] = null;
        if (empty($data['position'])) $data['position'] = null;
        if (empty($data['notes'])) $data['notes'] = null;

        $validator = Validator::make($data, [
            'club_id' => 'sometimes|required|exists:clubs,id',
            'joined_at' => 'sometimes|nullable|date_format:Y-m-d',
            'left_at' => 'nullable|date_format:Y-m-d|after:joined_at',
            'position' => 'nullable|string|max:255',
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
        $membership->load('club');

        return response()->json([
            'success' => true,
            'message' => 'Членство в клубе успешно обновлено',
            'data' => $membership
        ]);
    }

    /**
     * Удалить членство в клубе
     */
    public function destroy(Request $request, Person $person, $membershipId): JsonResponse
    {
        $membership = PersonClubMembership::where('id', $membershipId)
            ->where('person_id', $person->id)
            ->firstOrFail();

        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'Членство в клубе успешно удалено'
        ]);
    }

    /**
     * Завершить активное членство в клубе
     */
    public function leave(Request $request, Person $person, $membershipId): JsonResponse
    {
        $membership = PersonClubMembership::where('id', $membershipId)
            ->where('person_id', $person->id)
            ->firstOrFail();

        if ($membership->left_at) {
            return response()->json([
                'success' => false,
                'message' => 'Членство уже завершено'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'left_at' => 'required|date|after:joined_at|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $membership->update(['left_at' => $request->left_at]);
        $membership->load('club');

        return response()->json([
            'success' => true,
            'message' => 'Членство в клубе успешно завершено',
            'data' => $membership
        ]);
    }

    /**
     * Получить активные членства персоны в клубах
     */
    public function active(Person $person): JsonResponse
    {
        $memberships = $person->activeClubMemberships()
            ->with('club')
            ->orderBy('joined_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $memberships
        ]);
    }
}
