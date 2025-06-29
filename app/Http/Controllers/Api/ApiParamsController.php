<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\MenuSection;
use App\Models\Page;
use App\Models\Param;
use App\Models\PicParam;
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
            // Получаем все параметры из таблицы params
            $params = Param::all();

            // Преобразуем в формат name:value
            $paramsArray = [];
            foreach ($params as $param) {
                $paramsArray[$param->name] = $param->value;
            }

            // Получаем все параметры изображений из таблицы pic_params
            $picParams = PicParam::all();

            // Получаем APP_URL из .env
            $appUrl = config('app.url');

            // Преобразуем в формат name:value с добавлением APP_URL
            $imagesArray = [];
            foreach ($picParams as $picParam) {
                $imagesArray[$picParam->name] = $appUrl . '/storage/' . $picParam->value;
            }

            return response()->json([
                'params' => $paramsArray,
                'images' => $imagesArray
            ]);
        } catch (\Exception $e) {
            // Если есть ошибка, возвращаем пустые массивы
            return response()->json([
                'params' => [],
                'images' => []
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
     * Получить страницу по id или slug
     */
    public function getPage($id): JsonResponse
    {
        try {
            // Определяем, является ли параметр числом (ID) или строкой (slug)
            $isNumeric = is_numeric($id);

            // Получаем страницу из базы данных
            if ($isNumeric) {
                $page = Page::find($id);
            } else {
                $page = Page::where('slug', $id)->first();
            }

            if (!$page) {
                return response()->json([
                    'error' => 'Страница не найдена'
                ], 404);
            }

            // Получаем APP_URL из .env
            $appUrl = config('app.url');

            $pageData = [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'description' => $page->description,
                'keywords' => $page->keywords,
                'image' => $page->image ? $appUrl . '/storage/' . $page->image : null,
                'image_default' => $page->image_default ? $appUrl . '/storage/' . $page->image_default : null,
                'html' => $page->html,
                'icon' => $page->icon
            ];

            return response()->json($pageData);
        } catch (\Exception $e) {
            // Если есть ошибка, возвращаем ошибку
            return response()->json([
                'error' => 'Ошибка при получении страницы',
                'message' => $e->getMessage()
            ], 500);
        }
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
