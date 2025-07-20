<?php

namespace App\Http\Controllers;

use App\Models\ImageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageTemplateController extends Controller
{
    public function index(Request $request)
    {
                try {
            Log::info('ImageTemplateController@index called');
            $type = $request->get('type');
            Log::info('Type parameter: ' . $type);

            $query = ImageTemplate::query();
            if ($type) {
                $query->where('type', $type);
            }

            $results = $query->orderBy('id', 'desc')->get();
            Log::info('Found templates count: ' . $results->count());
            Log::info('Templates data: ' . $results->toJson());

            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Error in ImageTemplateController@index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
        Log::info('Template created with ID: ' . $template->id);
        Log::info('Template data: ' . $template->toJson());

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
