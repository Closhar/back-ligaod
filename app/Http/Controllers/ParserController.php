<?php

namespace App\Http\Controllers;

use App\Models\ParserTemplate;
use App\Models\ParserField;
use App\Services\ParserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ParserController extends Controller
{
    public function __construct(
        private ParserService $parserService
    ) {}

    public function index(): View
    {
        $templates = ParserTemplate::with('fields')->orderBy('created_at', 'desc')->get();

        return view('admin.parser.index', compact('templates'));
    }

    public function create(): View
    {
        return view('admin.parser.create');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'url_pattern' => 'required|string',
            'conditions' => 'nullable|array',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.selector' => 'required|string',
            'fields.*.selector_type' => 'required|in:css,xpath',
            'fields.*.data_type' => 'required|string',
            'fields.*.target_table' => 'nullable|string',
            'fields.*.target_field' => 'nullable|string',
            'fields.*.update_strategy' => 'required|in:insert,update,upsert',
            'fields.*.is_required' => 'boolean',
            'fields.*.order' => 'integer',
        ]);

        $template = ParserTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'url_pattern' => $validated['url_pattern'],
            'conditions' => $validated['conditions'] ?? [],
            'is_active' => true,
        ]);

        foreach ($validated['fields'] as $fieldData) {
            $template->fields()->create($fieldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'template_id' => $template->id,
        ]);
    }

    public function show(ParserTemplate $template): View
    {
        $template->load('fields', 'logs');

        return view('admin.parser.show', compact('template'));
    }

    public function edit(ParserTemplate $template): View
    {
        $template->load('fields');

        return view('admin.parser.edit', compact('template'));
    }

    public function update(Request $request, ParserTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'url_pattern' => 'required|string',
            'conditions' => 'nullable|array',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.selector' => 'required|string',
            'fields.*.selector_type' => 'required|in:css,xpath',
            'fields.*.data_type' => 'required|string',
            'fields.*.target_table' => 'nullable|string',
            'fields.*.target_field' => 'nullable|string',
            'fields.*.update_strategy' => 'required|in:insert,update,upsert',
            'fields.*.is_required' => 'boolean',
            'fields.*.order' => 'integer',
        ]);

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'url_pattern' => $validated['url_pattern'],
            'conditions' => $validated['conditions'] ?? [],
        ]);

        // Удаляем старые поля
        $template->fields()->delete();

        // Создаем новые поля
        foreach ($validated['fields'] as $fieldData) {
            $template->fields()->create($fieldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
        ]);
    }

    public function destroy(ParserTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    public function test(Request $request, ParserTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        $result = $this->parserService->testTemplate($template, $validated['url']);

        return response()->json($result);
    }

    public function parse(Request $request, ParserTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        $log = $this->parserService->parseUrl($template, $validated['url']);

        return response()->json([
            'success' => $log->status === 'success',
            'message' => $log->status === 'success'
                ? 'Data parsed and saved successfully'
                : 'Failed to parse data',
            'log_id' => $log->id,
            'records_created' => $log->records_created,
            'records_updated' => $log->records_updated,
        ]);
    }

    public function toggleActive(ParserTemplate $template): JsonResponse
    {
        $template->update(['is_active' => !$template->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $template->is_active,
            'message' => $template->is_active
                ? 'Template activated'
                : 'Template deactivated',
        ]);
    }
}
