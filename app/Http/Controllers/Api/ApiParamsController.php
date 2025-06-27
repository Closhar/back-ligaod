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
            // Получаем все активные разделы меню с их страницами
            $sections = MenuSection::active()
                ->with(['activeAdminPages' => function ($query) {
                    $query->ordered();
                }])
                ->ordered()
                ->get();

            // Получаем страницы без раздела
            $pagesWithoutSection = AdminPage::inMenu()
                ->whereNull('menu_section_id')
                ->ordered()
                ->get();

            $menu = [];

            // Добавляем разделы с их страницами
            foreach ($sections as $section) {
                if ($section->activeAdminPages->count() > 0) {
                    $sectionMenu = [
                        'title' => $section->name,
                        'icon' => $section->icon ?: 'fluent:folder-list-20-filled',
                        'submenu' => []
                    ];

                    foreach ($section->activeAdminPages as $page) {
                        $sectionMenu['submenu'][] = [
                            'title' => $page->title,
                            'icon' => $page->icon ?: 'fluent:document-20-filled',
                            'link' => '/' . $page->slug
                        ];
                    }

                    $menu[] = $sectionMenu;
                }
            }

            // Добавляем страницы без раздела
            foreach ($pagesWithoutSection as $page) {
                $menu[] = [
                    'title' => $page->title,
                    'icon' => $page->icon ?: 'fluent:document-20-filled',
                    'slug' => $page->slug
                ];
            }

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
            // Получаем страницу админки из базы данных
            $page = AdminPage::find($id);

            if (!$page) {
                return response()->json([
                    'error' => 'Страница не найдена'
                ], 404);
            }

            $pageData = [
                'id' => $page->id,
                'title' => $page->title,
                'description' => $page->description ?: 'Описание страницы',
                'icon' => $page->icon ?: 'fluent:document-20-filled',
                'breadcrumbs' => [
                    [
                        'id' => 1,
                        'title' => 'Главная',
                        'icon' => 'fluent:home-20-filled',
                        'slug' => '/'
                    ],
                    [
                        'id' => $page->id,
                        'title' => $page->title,
                        'icon' => $page->icon ?: 'fluent:document-20-filled',
                        'slug' => '/' . $page->slug
                    ]
                ]
            ];

            return response()->json($pageData);
        } catch (\Exception $e) {
            // Если есть ошибка, возвращаем данные по умолчанию
            return response()->json([
                'id' => $id,
                'title' => 'Страница',
                'description' => 'Описание страницы',
                'icon' => 'fluent:document-20-filled',
                'breadcrumbs' => []
            ]);
        }
    }
}