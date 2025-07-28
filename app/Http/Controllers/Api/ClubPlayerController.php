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
            'amplua_id' => 'nullable|exists:ampluas,id',
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
            // Уже есть членство — просто добавляем амплуа, если его нет
            $ampluaId = $data['amplua_id'] ?? null;
            if (!$ampluaId) {
                $activeAmplua = $person->activeAmpluaMemberships()->first();
                if ($activeAmplua) {
                    $ampluaId = $activeAmplua->amplua_id;
                }
            }
            if (!$ampluaId) {
                return response()->json([
                    'success' => false,
                    'errors' => ['amplua_id' => ['У игрока не найдено амплуа, выберите амплуа вручную.']]
                ], 422);
            }
            $existingAmplua = $person->activeAmpluaMemberships()
                ->where('amplua_id', $ampluaId)
                ->first();
            if (!$existingAmplua) {
                $ampluaMembership = PersonAmpluaMembership::create([
                    'person_id' => $person->id,
                    'amplua_id' => $ampluaId,
                    'started_at' => now(),
                ]);
            } else {
                $ampluaMembership = $existingAmplua;
            }
            return response()->json([
                'success' => true,
                'message' => 'Амплуа добавлено существующему члену клуба',
                'data' => [
                    'membership' => $existingMembership,
                    'amplua_membership' => $ampluaMembership,
                ]
            ], 200);
        }

        // Создаем членство в клубе
        $membership = PersonClubMembership::create([
            'person_id' => $person->id,
            'club_id' => $club->id,
            'joined_at' => $data['joined_at'] ?? null,
        ]);

        // Логика выбора амплуа
        $ampluaId = $data['amplua_id'] ?? null;
        if (!$ampluaId) {
            // Если не передан amplua_id, ищем активное амплуа у игрока
            $activeAmplua = $person->activeAmpluaMemberships()->first();
            if ($activeAmplua) {
                $ampluaId = $activeAmplua->amplua_id;
            }
        }
        if (!$ampluaId) {
            return response()->json([
                'success' => false,
                'errors' => ['amplua_id' => ['У игрока не найдено амплуа, выберите амплуа вручную.']]
            ], 422);
        }
        // Проверяем, есть ли уже активное амплуа у игрока
        $existingAmplua = $person->activeAmpluaMemberships()
            ->where('amplua_id', $ampluaId)
            ->first();
        if (!$existingAmplua) {
            // Создаем амплуа
            $ampluaMembership = PersonAmpluaMembership::create([
                'person_id' => $person->id,
                'amplua_id' => $ampluaId,
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
     * Добавить сотрудника в клуб с должностью
     */
    public function addWithPosition(Request $request, Club $club): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'person_id' => 'required|exists:people,id',
            'position_id' => 'required|exists:positions,id',
            'joined_at' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'started_at' => 'nullable|date_format:Y-m-d|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $person = Person::findOrFail($data['person_id']);

        // Проверяем, есть ли уже активное членство в клубе
        $existingMembership = $person->clubMemberships()
            ->where('club_id', $club->id)
            ->whereNull('left_at')
            ->first();
        if (!$existingMembership) {
            // Создаём членство в клубе
            $existingMembership = PersonClubMembership::create([
                'person_id' => $person->id,
                'club_id' => $club->id,
                'joined_at' => $data['joined_at'] ?? null,
            ]);
        }

        // Проверяем, есть ли уже активная должность у персоны
        $existingPosition = $person->activePositionMemberships()
            ->where('position_id', $data['position_id'])
            ->first();
        if (!$existingPosition) {
            $positionMembership = $person->positionMemberships()->create([
                'position_id' => $data['position_id'],
                'started_at' => $data['started_at'] ?? now(),
            ]);
        } else {
            $positionMembership = $existingPosition;
        }

        return response()->json([
            'success' => true,
            'message' => 'Сотрудник успешно добавлен в клуб с должностью',
            'data' => [
                'membership' => $existingMembership,
                'position_membership' => $positionMembership,
            ]
        ], 200);
    }

    /**
     * Получить список игроков клуба с амплуа и персональными данными
     */
    public function players(Request $request, Club $club): JsonResponse
    {
        $memberships = PersonClubMembership::with(['person'])
            ->where('club_id', $club->id)
            ->whereNull('left_at')
            ->get();

        // Фильтруем только игроков (спортсменов) - тех, у кого есть активные амплуа
        $playerMemberships = $memberships->filter(function ($membership) {
            $person = $membership->person;
            if (!$person) return false;

            // Проверяем, есть ли у персоны активные амплуа
            return $person->activeAmpluaMemberships()->exists();
        });

        // Для каждого игрока подгружаем ВСЕ амплуа
        $result = $playerMemberships->map(function ($membership) {
            $person = $membership->person;
            $ampluas = $person ? $person->ampluaMemberships()->with('amplua')->get()->map(function($ampluaMembership) {
                return [
                    'id' => $ampluaMembership->amplua?->id,
                    'name' => $ampluaMembership->amplua?->name,
                    'started_at' => $ampluaMembership->started_at,
                    'ended_at' => $ampluaMembership->ended_at,
                ];
            }) : collect();
            return [
                'id' => $membership->id,
                'person_id' => $membership->person_id,
                'club_id' => $membership->club_id,
                'joined_at' => $membership->joined_at,
                'left_at' => $membership->left_at,
                'position' => $membership->position,
                'notes' => $membership->notes,
                'created_at' => $membership->created_at,
                'updated_at' => $membership->updated_at,
                'person' => $person,
                'ampluas' => $ampluas,
            ];
        });

        return response()->json($result);
    }
}
