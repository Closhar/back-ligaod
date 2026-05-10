<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $isAsync = $request->input('type') === 'async';
        $search = trim((string) $request->input('q', ''));

        $query = Document::query()
            ->withCount('articles')
            ->when($request->has('in_about') && $request->input('in_about') !== '', function ($query) use ($request) {
                $query->where('in_about', filter_var($request->input('in_about'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%")
                        ->orWhere('file_path', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($isAsync) {
            return response()->json($query
                ->limit((int) $request->input('limit', 50))
                ->get(['id', 'title', 'file_path', 'original_name', 'mime_type', 'size']));
        }

        $perPage = (int) $request->input('per_page', 50);
        $documents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $documents->items(),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    public function about(): JsonResponse
    {
        $documents = Document::query()
            ->where('in_about', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:51200'],
            'in_about' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store('docs', 'public');

        $document = Document::create([
            'title' => $request->input('title'),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'in_about' => $request->boolean('in_about'),
            'sort_order' => (int) $request->input('sort_order', 500),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Документ успешно создан',
            'data' => $document,
        ], 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $document->load(['articles' => function ($query) {
                $query->select('articles.id', 'articles.title', 'articles.slug', 'articles.data')
                    ->orderByDesc('data');
            }])->loadCount('articles'),
        ]);
    }

    public function syncArticles(Request $request, Document $document): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'article_ids' => ['array'],
            'article_ids.*' => ['integer', 'exists:articles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $document->articles()->sync($request->input('article_ids', []));

        return response()->json([
            'success' => true,
            'message' => 'Связи документа со статьями обновлены',
            'data' => $document->fresh()->loadCount('articles'),
        ]);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:51200'],
            'in_about' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [];

        if ($request->has('title')) {
            $data['title'] = $request->input('title');
        }

        if ($request->has('in_about')) {
            $data['in_about'] = $request->boolean('in_about');
        }

        if ($request->has('sort_order')) {
            $data['sort_order'] = (int) $request->input('sort_order', 500);
        }

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($document->file_path);

            $file = $request->file('file');
            $data['file_path'] = $file->store('docs', 'public');
            $data['original_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getClientMimeType();
            $data['size'] = $file->getSize();
        }

        $document->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Документ успешно обновлен',
            'data' => $document->fresh(),
        ]);
    }

    public function destroy(Document $document): JsonResponse
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Документ успешно удален',
        ]);
    }
}
