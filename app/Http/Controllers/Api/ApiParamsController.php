<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiGenderRequest;
use App\Models\AdminPage;
use App\Models\Age;
use App\Models\Gender;
use App\Models\Page;
use App\Models\Param;
use App\Models\PicParam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Str;
use Validator;

class ApiParamsController extends Controller
{

        /**
     * Получить меню админки с разделами
     */
    public function getAdminMenu(): JsonResponse
    {
        // Получаем все активные разделы меню с их страницами
        $sections = MenuSection::active()
            ->ordered()
            ->with(['adminPages' => function ($query) {
                $query->inMenu()->ordered();
            }])
            ->get();

        // Получаем страницы без раздела
        $pagesWithoutSection = AdminPage::inMenu()
            ->withActiveSections()
            ->whereNull('menu_section_id')
            ->ordered()
            ->get();

        $menu = [];

        // Добавляем разделы с их страницами
        foreach ($sections as $section) {
            if ($section->adminPages->count() > 0) {
                $menu[] = [
                    'title' => $section->name,
                    'icon' => $section->icon,
                    'submenu' => $section->adminPages->map(function ($page) {
                        return [
                            'title' => $page->title,
                            'icon' => $page->icon,
                            'link' => '/' . $page->slug
                        ];
                    })->toArray()
                ];
            }
        }

        // Добавляем страницы без раздела как отдельные пункты меню
        foreach ($pagesWithoutSection as $page) {
            $menu[] = [
                'title' => $page->title,
                'icon' => $page->icon,
                'slug' => $page->slug
            ];
        }

        return response()->json($menu);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): array
    {
        $p = Param::query()
            ->select([
                'name',
                'value'
            ])
            ->get()
            ->toArray();
        $ip = PicParam::query()
            ->select([
                'name',
                'value',
                DB::raw("CONCAT('" . config('app.url') . "', '/storage/', value) AS path")
            ])
            ->get()
            ->toArray();
        return [
            'params' => $p,
            'images' => $ip,
        ];;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Age $gender, $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Gender $gender)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gender $gender)
    {
        //
    }

    public function getTitle(Request $request): JsonResponse
    {

        // Валидация входных параметров
        $validator = Validator::make($request->all(), [
            'table' => 'required',
            'value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Параметры table и value обязательны'], 400);
        }

        $table = $request->query('table');
        $value = $request->query('value');

        // Проверка, что таблица существует (опционально)
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return response()->json(['error' => 'Таблица не найдена'], 404);
        }

        try {
            // Выполнение запроса к базе данных
            $result = DB::table($table)
                ->where('slug', $value)
                ->orWhere('id', $value)
                ->select('title')
                ->first();

            if ($result) {
                return response()->json(['title' => $result->title]);
            } else {
                return response()->json(['error' => 'Запись не найдена'], 404);
            }
        } catch (\Exception $e) {
            // Логирование ошибки
            \Log::error('Ошибка при выполнении запроса: ' . $e->getMessage());
            return response()->json(['error' => 'Внутренняя ошибка сервера'], 500);
        }
    }

    public function getPage($id, Request $request): JsonResponse
    {
        $s = $request->query('p');

        $id2 = $id;
        if ($id == 'articles') $id2 = 'news';

        // Находим страницу по ID или slug
        $page = Page::query()
            ->where('id', $id2)
            ->orWhere('slug', $id2)
            ->first(); // Получаем первую запись, соответствующую условию

        // Если страница не найдена, возвращаем 404
        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }


        $p = null;
        if ($s) {
            $modelClassName = 'App\\Models\\' . Str::singular(ucfirst($id));
            if (class_exists($modelClassName)) {
                $p = $modelClassName::where('id', $s)
                    ->orWhere('slug', $s)
                    ->first()
                    ->event_name;
            }
        }

        // Формируем ответ в нужной структуре
        return response()->json([
            'title' => $page->title,
            'description' => $page->description,
            'page_image' => $page->page_image,
            'default_page_image' => $page->default_page_image,
            'icon' => $page->icon,
            'slug' => $page->slug,
            'html' => $page->html,
            'page_title' => $p,
            'seo' => [
                'title' => $page->title,
                'description' => $page->description,
                'keywords' => $page->keywords,
                'ogTitle' => $page->title,
                'ogDescription' => $page->description,
                'ogImage' => $page->page_image,
                'twitterCard' => 'summary_large_image',
                'twitterTitle' => $page->title,
                'twitterDescription' => $page->description,
                'twitterImage' => $page->page_image,
            ],
        ]);
    }

    public function getAdminPage($id, Request $request): JsonResponse
    {
        $p = $request->query('p');
        $breadcrumbs = null;

        // Находим страницу по ID или slug
        $page = AdminPage::query()
            ->where('id', $id)
            ->first(); // Получаем первую запись, соответствующую условию

        // Если страница не найдена, возвращаем 404
        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        if ($p) {
            $breadcrumbs = null;
        }

        // Формируем ответ в нужной структуре
        return response()->json([
            'id' => $page->id,
            'title' => $page->title,
            'description' => $page->description,
            'image' => $page->image,
            'icon' => $page->icon,
            'page_image' => $page->page_image,
            'slug' => $page->slug,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    public function getAdminmenu(): array
    {

        // Находим страницу по ID или slug
        $page = AdminPage::query()
            ->where('menu', 1)
            ->get()
            ->toArray();

        return $page;
    }

}
