<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\MenuSection;
use Illuminate\Http\JsonResponse;

class ApiParamsController extends Controller
{
    /**
     * Тестовый метод для проверки работы API
     */
    public function test(): JsonResponse
    {
        return response()->json([
            'message' => 'API работает!',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Получить меню админки с разделами
     */
    public function getAdminMenu(): JsonResponse
    {
        try {
            // Временно возвращаем тестовые данные
            $menu = [
                [
                    'title' => 'Спорт',
                    'icon' => 'heroicons:sport',
                    'submenu' => [
                        [
                            'title' => 'Виды спорта',
                            'icon' => 'heroicons:list-bullet',
                            'link' => '/sports'
                        ],
                        [
                            'title' => 'События',
                            'icon' => 'heroicons:calendar',
                            'link' => '/events'
                        ]
                    ]
                ],
                [
                    'title' => 'Галереи',
                    'icon' => 'heroicons:photo',
                    'slug' => 'galleries'
                ]
            ];

            return response()->json($menu);
        } catch (\Exception $e) {
            // Если есть ошибка, возвращаем пустое меню
            return response()->json([]);
        }
    }

    /**
     * Получить параметры
     */
    public function index(): JsonResponse
    {
        try {
            // Возвращаем параметры и изображения для фронтенда
            $params = [
                ['name' => 'adminka_name', 'value' => 'Админка'],
                ['name' => 'site_name', 'value' => 'Спортивный портал'],
                ['name' => 'site_description', 'value' => 'Информационный портал о спортивных событиях'],
            ];

            $images = [
                ['name' => 'default_user', 'path' => '/images/default-avatar.png'],
                ['name' => 'logo', 'path' => '/images/logo.png'],
            ];

            return response()->json([
                'params' => $params,
                'images' => $images
            ]);
        } catch (\Exception $e) {
            // Если есть ошибка, возвращаем значения по умолчанию
            return response()->json([
                'params' => [
                    ['name' => 'adminka_name', 'value' => 'Админка'],
                    ['name' => 'site_name', 'value' => 'Спортивный портал'],
                ],
                'images' => [
                    ['name' => 'default_user', 'path' => '/images/default-avatar.png'],
                ]
            ]);
        }
    }

    /**
     * Получить заголовок
     */
    public function getTitle(): JsonResponse
    {
        // Здесь должна быть логика получения заголовка
        return response()->json(['message' => 'Title endpoint']);
    }

    /**
     * Получить страницу
     */
    public function getPage($id): JsonResponse
    {
        // Здесь должна быть логика получения страницы
        return response()->json(['message' => 'Page endpoint', 'id' => $id]);
    }

    /**
     * Получить страницу админки
     */
    public function getAdminPage($id): JsonResponse
    {
        try {
            // Возвращаем данные страницы админки
            $pageData = [
                'id' => $id,
                'title' => 'Главная страница',
                'description' => 'Главная страница админки',
                'icon' => 'heroicons:home',
                'breadcrumbs' => [
                    [
                        'id' => 1,
                        'title' => 'Главная',
                        'icon' => 'heroicons:home',
                        'slug' => '/'
                    ]
                ]
            ];

            return response()->json($pageData);
        } catch (\Exception $e) {
            // Если есть ошибка, возвращаем данные по умолчанию
            return response()->json([
                'id' => $id,
                'title' => 'Главная страница',
                'description' => 'Главная страница админки',
                'icon' => 'heroicons:home',
                'breadcrumbs' => []
            ]);
        }
    }
}