<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Person;
use App\Models\PersonAmpluaMembership;
use App\Models\PersonClubMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClubPlayerController extends Controller
{
    /**
     * Добавить игрока в команду с амплуа
     */
    public function addWithAmplua(Request $request, Club $club): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'person_id' => 'required|exists:people,id',
            'amplua_id' => 'required|exists:ampluas,id',
            'joined_at' => 'nullable|date_format:Y-m-d|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $person = Person::findOrFail($data['person_id']);

        // Проверяем, нет ли уже активного членства в этом клубе
        $existingMembership = $person->clubMemberships()
            ->where('club_id', $club->id)
            ->whereNull('left_at')
            ->first();
        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Персона уже является членом этого клуба'
            ], 422);
        }

        // Создаем членство в клубе
        $membership = PersonClubMembership::create([
            'person_id' => $person->id,
            'club_id' => $club->id,
            'joined_at' => $data['joined_at'] ?? null,
        ]);

        // Проверяем, есть ли уже активное амплуа у игрока
        $existingAmplua = $person->activeAmpluaMemberships()
            ->where('amplua_id', $data['amplua_id'])
            ->first();
        if (!$existingAmplua) {
            // Создаем амплуа
            $ampluaMembership = PersonAmpluaMembership::create([
                'person_id' => $person->id,
                'amplua_id' => $data['amplua_id'],
                'started_at' => now(),
            ]);
        } else {
            $ampluaMembership = $existingAmplua;
        }

        return response()->json([
            'success' => true,
            'message' => 'Игрок успешно добавлен в команду с амплуа',
            'data' => [
                'membership' => $membership,
                'amplua_membership' => $ampluaMembership,
            ]
        ], 201);
    }

    /**
     * Получить список игроков клуба с амплуа и персональными данными
     */
    public function players(Request $request, Club $club): JsonResponse
    {
        $memberships = PersonClubMembership::with(['person', 'amplua'])
            ->where('club_id', $club->id)
            ->whereNull('left_at')
            ->get();

        return response()->json($memberships);
    }
}
