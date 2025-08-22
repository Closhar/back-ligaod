<?php

namespace App\Http\Controllers;

use App\Models\ActionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $actionTypes = ActionType::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($actionTypes);
    }
}
