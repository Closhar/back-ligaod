<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\Age;
use App\Models\City;
use App\Models\Gender;
use Illuminate\Http\Request;

class ApiCityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): array
    {
        return City::all()->toArray();
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
    public function show(City $gender, $id): array
    {
        return City::where('id', $id)
            ->with(['clubs' => function ($query) {
                $query->select([
                    'clubs.id', // Явно указываем таблицу
                    'clubs.title',
                    'clubs.image',
                    'clubs.slug',
                    'clubs.city_id',
                    'clubs.age_id',
                    'clubs.gender_id',
                    'clubs.sport_id' // Для HasMany!!!!
                ])->with([
                    'city' => function ($cityQuery) {
                        $cityQuery->select(['id', 'title']);
                    },
                    'age' => function ($ageQuery) {
                        $ageQuery->select(['id', 'title_short']);
                    },
                    'gender' => function ($genderQuery) {
                        $genderQuery->select(['id', 'title', 'icon']);
                    }
                ]);
            },
            ])
            ->get()
            ->toArray();
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
