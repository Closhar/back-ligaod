<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Gender;
use App\Models\SportProperty;
use Illuminate\Http\Request;

class ApiGalleryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): array
    {
        return Gallery::all()->toArray();
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
    public function show(Gallery $gender, $id): array
    {
        return Gallery::where('id', $id)
            ->with(['main_image', 'images'])
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
