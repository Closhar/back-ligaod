<?php

namespace App\Http\Controllers;

use App\Models\ImageTemplate;
use Illuminate\Http\Request;

class ImageTemplateController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('type');
        $query = ImageTemplate::query();
        if ($type) {
            $query->where('type', $type);
        }
        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'path' => 'required|string',
            'preview_path' => 'nullable|string',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
        ]);
        $template = ImageTemplate::create($data);
        return response()->json($template, 201);
    }

    public function show(ImageTemplate $imageTemplate)
    {
        return response()->json($imageTemplate);
    }

    public function update(Request $request, ImageTemplate $imageTemplate)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'type' => 'sometimes|required|string',
            'path' => 'sometimes|required|string',
            'preview_path' => 'nullable|string',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
        ]);
        $imageTemplate->update($data);
        return response()->json($imageTemplate);
    }

    public function destroy(ImageTemplate $imageTemplate)
    {
        $imageTemplate->delete();
        return response()->json(['success' => true]);
    }
}
