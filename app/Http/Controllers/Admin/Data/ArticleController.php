<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $searchQuery = $request->input('q');
        $regionId = $request->input('region_id');
        $published = $request->input('published');
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');
        $id = $request->input('id');

        $query = Article::query()->with('region');

        if ($id) {
            $query->where('id', $id);
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('title', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('description', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('content', 'LIKE', "%{$searchQuery}%");
            });
        }

        if ($regionId) {
            $query->where('region_id', $regionId);
        }

        if ($published !== null) {
            $query->where('published', $published);
        }

        $query->orderBy($sortField, $sortDirection);
        $articles = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'current_page' => $articles->currentPage(),
            'data' => $articles->items(),
            'first_page_url' => $articles->url(1),
            'from' => $articles->firstItem(),
            'last_page' => $articles->lastPage(),
            'last_page_url' => $articles->url($articles->lastPage()),
            'links' => $articles->links(),
            'next_page_url' => $articles->nextPageUrl(),
            'path' => $articles->path(),
            'per_page' => $articles->perPage(),
            'prev_page_url' => $articles->previousPageUrl(),
            'to' => $articles->lastItem(),
            'total' => $articles->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'data' => 'required|date',
                'slug' => 'required|string|max:255',
                'content' => 'required|string',
                'region_id' => 'nullable|integer|exists:regions,id',
                'published' => 'boolean',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            $validated['data'] = date('Y-m-d H:i:s', strtotime($validated['data']));

            $article = Article::create($validated);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('articles', 'public');
                $article->image = $path;
                $article->save();
            }

            return response()->json($article, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $article = Article::select([
                'id', 'title', 'description', 'data', 'slug', 'region_id',
                'published', 'image', 'content', 'created_at', 'updated_at'
            ])->with([
                'region',
                'sports',
                'clubs',
                'arenas',
                'competitions',
                'events',
                'galleries',
                'videos'
            ])->findOrFail($id);

            return response()->json($article);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Статья не найдена'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Внутренняя ошибка сервера',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if ($request->input('published') === 'on') {
            return response()->json([], 200);
        }

        try {
            $article = Article::findOrFail($id);

            $validated = $request->validate([
                'title' => 'string|max:255',
                'data' => 'date_format:Y-m-d H:i:s',
                'slug' => 'string|max:255',
                'description' => 'string|max:1000',
                'content' => 'string',
                'region_id' => 'nullable|integer|exists:regions,id',
                'published' => 'boolean',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($request->hasFile('image')) {
                if ($article->image) {
                    Storage::disk('public')->delete($article->image);
                }
                $path = $request->file('image')->store('articles', 'public');
                $validated['image'] = $path;
            }

            $article->update($validated);

            return response()->json([
                'success' => true,
                'data' => $article,
                'message' => 'Updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $article = Article::findOrFail($id);

            if ($article->image) {
                Storage::disk('public')->delete($article->image);
            }

            $article->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function uploadImage(Request $request, $id)
    {
        try {
            $article = Article::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'image' => [
                    'required',
                    'image',
                    'mimes:jpeg,png,jpg,gif,webp',
                    'max:2048'
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($article->image) {
                Storage::disk('public')->delete($article->image);
            }

            $path = $request->file('image')->store('articles', 'public');
            $article->image = $path;
            $article->save();

            return response()->json([
                'success' => true,
                'image_path' => $path,
                'full_path' => Storage::disk('public')->url($path),
                'message' => 'Изображение успешно загружено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteImage($id)
    {
        try {
            $article = Article::findOrFail($id);

            if (!$article->image) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет изображения для удаления'
                ], 404);
            }

            Storage::disk('public')->delete($article->image);
            $article->image = null;
            $article->save();

            return response()->json([
                'success' => true,
                'message' => 'Изображение успешно удалено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении изображения: ' . $e->getMessage()
            ], 500);
        }
    }
}
