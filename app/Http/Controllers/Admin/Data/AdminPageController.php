<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\MenuSection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AdminPageController extends Controller
{
    /**
     * Получить список страниц админки
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdminPage::with('menuSection');

        // Поиск
        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Фильтр по меню
        if ($request->has('menu') && $request->menu !== null) {
            $query->where('menu', $request->menu);
        }

        // Фильтр по разделу меню
        if ($request->has('menu_section_id') && $request->menu_section_id !== null) {
            $query->where('menu_section_id', $request->menu_section_id);
        }

        // Сортировка
        $sortField = $request->get('sort_field', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Пагинация
        $perPage = $request->get('per_page', 30);
        $pages = $query->paginate($perPage);

        return response()->json($pages);
    }

    /**
     * Получить страницу админки по ID
     */
    public function show($id): JsonResponse
    {
        $page = AdminPage::with('menuSection')->findOrFail($id);
        return response()->json($page);
    }

    /**
     * Создать новую страницу админки
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:admin_pages',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'menu' => 'boolean',
            'menu_section_id' => 'nullable|exists:menu_sections,id',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $page = AdminPage::create($request->all());
        $page->load('menuSection');

        return response()->json($page, 201);
    }

    /**
     * Обновить страницу админки
     */
    public function update(Request $request, $id): JsonResponse
    {
        $page = AdminPage::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:admin_pages,slug,' . $id,
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'menu' => 'boolean',
            'menu_section_id' => 'nullable|exists:menu_sections,id',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $page->update($request->all());
        $page->load('menuSection');

        return response()->json($page);
    }

    /**
     * Удалить страницу админки
     */
    public function destroy($id): JsonResponse
    {
        $page = AdminPage::findOrFail($id);
        $page->delete();

        return response()->json(['message' => 'Страница админки удалена']);
    }

    /**
     * Получить список разделов меню для селекта
     */
    public function getMenuSections(): JsonResponse
    {
        $sections = MenuSection::active()
            ->ordered()
            ->select('id', 'name')
            ->get();

        return response()->json($sections);
    }
}