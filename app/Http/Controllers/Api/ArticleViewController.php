<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleViewController extends Controller
{
    /**
     * Записать просмотр статьи
     * Маршрут: POST /api/v1/article_count/{slug}/views
     */
    public function recordView(Request $request, string $slug): JsonResponse
    {
        try {
            $article = Article::where('slug', $slug)
                ->select(['id', 'slug', 'title', 'views'])
                ->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Статья не найдена'
                ], 404);
            }

            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $sessionId = $request->session()->getId();

            // Проверяем, не был ли уже просмотр с этого IP в течение последних 24 часов
            if (!$article->hasRecentViewFromIp($ipAddress)) {
                $article->recordView($ipAddress, $userAgent, $sessionId);
            }

            // Получаем обновленное количество просмотров
            $article->refresh();

            return response()->json([
                'success' => true,
                'views_count' => $article->getViewsCount(),
                'message' => 'Просмотр записан'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при записи просмотра: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику просмотров статьи
     * Маршрут: GET /api/v1/article_count/{slug}/views/stats
     */
    public function getViewsStats(Request $request, string $slug): JsonResponse
    {
        try {
            $article = Article::where('slug', $slug)
                ->select(['id', 'slug', 'title', 'views'])
                ->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Статья не найдена'
                ], 404);
            }

            $period = $request->get('period', 'day'); // hour, day, week, month
            $stats = $article->getViewsStats($period);

            return response()->json([
                'success' => true,
                'article_id' => $article->id,
                'article_title' => $article->title,
                'total_views' => $article->getViewsCount(),
                'period_stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить количество просмотров статьи
     * Маршрут: GET /api/v1/article_count/{slug}/views
     */
    public function getViewsCount(string $slug): JsonResponse
    {
        try {
            $article = Article::where('slug', $slug)
                ->select(['id', 'slug', 'title', 'views'])
                ->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Статья не найдена'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'views_count' => $article->getViewsCount()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении количества просмотров: ' . $e->getMessage()
            ], 500);
        }
    }
}