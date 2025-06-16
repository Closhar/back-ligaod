<?php

namespace App\Http\Controllers;

use App\Models\PromptTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromptTemplateController extends Controller
{
    public function index()
    {
        $templates = PromptTemplate::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'prompt' => 'required|string',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $template = PromptTemplate::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $template
        ], 201);
    }

    public function update(Request $request, PromptTemplate $template)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'prompt' => 'required|string',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $template->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    public function destroy(PromptTemplate $template)
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Шаблон успешно удален'
        ]);
    }
}