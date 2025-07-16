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
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'image' => 'required|file|image|max:8192', // до 8 МБ
        ]);

        // Сохраняем файл
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('public/image-templates');
            $data['path'] = str_replace('public/', '/storage/', $path);
            $data['preview_path'] = $data['path'];
        } else {
            return response()->json(['error' => 'Файл не загружен'], 422);
        }

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
