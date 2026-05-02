<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArticleTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArticleTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ArticleTag::query()
            ->select(['id', 'title', 'slug'])
            ->orderBy('title');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('type') || $request->input('type') === 'async') {
            return response()->json($query->limit((int) $request->input('limit', 50))->get());
        }

        return response()->json($query->paginate((int) $request->input('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:120'],
                'slug' => ['nullable', 'string', 'max:160', Rule::unique('article_tags', 'slug')],
            ]);

            $title = trim($validated['title']);
            $slug = trim((string) ($validated['slug'] ?? '')) ?: Str::slug($title);
            if ($slug === '') {
                $slug = Str::slug(Str::transliterate($title));
            }

            $baseSlug = $slug ?: 'tag';
            $slug = $baseSlug;
            $counter = 2;
            while (ArticleTag::query()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $tag = ArticleTag::query()->create([
                'title' => $title,
                'slug' => $slug,
            ]);

            return response()->json($tag, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
