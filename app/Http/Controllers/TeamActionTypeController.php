<?php

namespace App\Http\Controllers;

use App\Models\TeamActionType;
use Illuminate\Http\JsonResponse;

class TeamActionTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $teamActionTypes = TeamActionType::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($teamActionTypes);
    }
}
