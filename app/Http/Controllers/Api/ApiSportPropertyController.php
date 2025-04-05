<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gender;
use App\Models\SportProperty;
use Illuminate\Http\Request;

class ApiSportPropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): array
    {
        return SportProperty::all()->toArray();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(SportProperty $gender, $id): array
    {
        return SportProperty::where('id', $id)->with('sports')->get()->toArray();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Gender $gender)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gender $gender)
    {
        //
    }
}
