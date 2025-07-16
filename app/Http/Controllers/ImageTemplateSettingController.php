<?php

namespace App\Http\Controllers;

use App\Models\ImageTemplateSetting;
use Illuminate\Http\Request;

class ImageTemplateSettingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(ImageTemplateSetting::orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'width' => 'required|integer',
            'height' => 'required|integer',
            'description' => 'nullable|string',
        ]);
        $setting = ImageTemplateSetting::create($data);
        return response()->json($setting, 201);
    }

    public function show(ImageTemplateSetting $imageTemplateSetting)
    {
        return response()->json($imageTemplateSetting);
    }

    public function update(Request $request, ImageTemplateSetting $imageTemplateSetting)
    {
        $data = $request->validate([
            'type' => 'sometimes|required|string',
            'width' => 'sometimes|required|integer',
            'height' => 'sometimes|required|integer',
            'description' => 'nullable|string',
        ]);
        $imageTemplateSetting->update($data);
        return response()->json($imageTemplateSetting);
    }

    public function destroy(ImageTemplateSetting $imageTemplateSetting)
    {
        $imageTemplateSetting->delete();
        return response()->json(['success' => true]);
    }
}
