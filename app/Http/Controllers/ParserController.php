<?php

namespace App\Http\Controllers;

use App\Models\ParserTemplate;
use App\Models\ParserField;
use App\Services\ParserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ParserController extends Controller
{
    public function __construct(
        private ParserService $parserService
    ) {}

    public function index(): JsonResponse
    {
        $templates = ParserTemplate::with('fields')->orderBy('created_at', 'desc')->get();

        return response()->json($templates);
    }

    public function create(): JsonResponse
    {
        return response()->json(['message' => 'Create form endpoint']);
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
            // Новые поля умного парсинга
            'fields.*.search_context' => 'nullable|string',
            'fields.*.search_phrase' => 'nullable|string',
            'fields.*.value_separator' => 'nullable|string',
            'fields.*.result_format' => 'nullable|string',
            'fields.*.team_identification' => 'nullable|array',
        ]);

        $template = ParserTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'url_pattern' => $validated['url_pattern'],
            'conditions' => $validated['conditions'] ?? [],
            'headers' => $validated['headers'] ?? null,
            'is_active' => true,
        ]);

        foreach ($validated['fields'] as $fieldData) {
            // Обрабатываем team_identification - убеждаемся что это массив
            $teamIdentification = null;
            if (isset($fieldData['team_identification'])) {
                if (is_array($fieldData['team_identification'])) {
                    $teamIdentification = $fieldData['team_identification'];
                } elseif (is_string($fieldData['team_identification'])) {
                    $decoded = json_decode($fieldData['team_identification'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $teamIdentification = $decoded;
                    }
                }
            }

            $template->fields()->create([
                'name' => $fieldData['name'],
                'selector' => $fieldData['selector'],
                'selector_type' => $fieldData['selector_type'],
                'data_type' => $fieldData['data_type'],
                'target_table' => $fieldData['target_table'] ?? null,
                'target_field' => $fieldData['target_field'] ?? null,
                'update_strategy' => $fieldData['update_strategy'],
                'is_required' => $fieldData['is_required'] ?? false,
                'order' => $fieldData['order'] ?? 0,
                // Новые поля умного парсинга
                'search_context' => $fieldData['search_context'] ?? null,
                'search_phrase' => $fieldData['search_phrase'] ?? null,
                'value_separator' => $fieldData['value_separator'] ?? null,
                'result_format' => $fieldData['result_format'] ?? null,
                'team_identification' => $teamIdentification,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'template_id' => $template->id,
        ]);
    }

    public function show(ParserTemplate $template): JsonResponse
    {
        $template->load('fields', 'logs');

        return response()->json($template);
    }

    public function edit(ParserTemplate $template): JsonResponse
    {
        $template->load('fields');

        return response()->json($template);
    }

    public function update(Request $request, ParserTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'url_pattern' => 'required|string',
            'conditions' => 'nullable|array',
            'headers' => 'nullable|array',
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
            // Новые поля умного парсинга
            'fields.*.search_context' => 'nullable|string',
            'fields.*.search_phrase' => 'nullable|string',
            'fields.*.value_separator' => 'nullable|string',
            'fields.*.result_format' => 'nullable|string',
            'fields.*.team_identification' => 'nullable|array',
        ]);

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'url_pattern' => $validated['url_pattern'],
            'conditions' => $validated['conditions'] ?? [],
            'headers' => $validated['headers'] ?? null,
        ]);

        // Удаляем старые поля
        $template->fields()->delete();

        // Создаем новые поля
        foreach ($validated['fields'] as $fieldData) {
            // Обрабатываем team_identification - убеждаемся что это массив
            $teamIdentification = null;
            if (isset($fieldData['team_identification'])) {
                if (is_array($fieldData['team_identification'])) {
                    $teamIdentification = $fieldData['team_identification'];
                } elseif (is_string($fieldData['team_identification'])) {
                    $decoded = json_decode($fieldData['team_identification'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $teamIdentification = $decoded;
                    }
                }
            }

            $template->fields()->create([
                'name' => $fieldData['name'],
                'selector' => $fieldData['selector'],
                'selector_type' => $fieldData['selector_type'],
                'data_type' => $fieldData['data_type'],
                'target_table' => $fieldData['target_table'] ?? null,
                'target_field' => $fieldData['target_field'] ?? null,
                'update_strategy' => $fieldData['update_strategy'],
                'is_required' => $fieldData['is_required'] ?? false,
                'order' => $fieldData['order'] ?? 0,
                // Новые поля умного парсинга
                'search_context' => $fieldData['search_context'] ?? null,
                'search_phrase' => $fieldData['search_phrase'] ?? null,
                'value_separator' => $fieldData['value_separator'] ?? null,
                'result_format' => $fieldData['result_format'] ?? null,
                'team_identification' => $teamIdentification,
            ]);
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

        try {
            $result = $this->parserService->testTemplate($template, $validated['url']);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
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
