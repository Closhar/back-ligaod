<?php

namespace App\Http\Controllers\Admin\Data;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\MenuSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPageController extends Controller
{
    /**
     * Получить список страниц админки
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdminPage::with(['menuSection' => function ($query) {
            $query->select('id', 'name');
        }]);

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
        $page = AdminPage::with(['menuSection' => function ($query) {
            $query->select('id', 'name');
        }])->findOrFail($id);
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

        // Устанавливаем значение по умолчанию для sort_order если не передано или пустое
        $data = $request->all();
        if (!isset($data['sort_order']) || $data['sort_order'] === null || $data['sort_order'] === '' || (empty($data['sort_order']) && $data['sort_order'] !== 0)) {
            $data['sort_order'] = 0;
        }

        $page = AdminPage::create($data);
        $page->load(['menuSection' => function ($query) {
            $query->select('id', 'name');
        }]);

        return response()->json($page, 201);
    }

    /**
     * Обновить страницу админки
     */
    public function update(Request $request, $id): JsonResponse
    {
        $page = AdminPage::findOrFail($id);

        // Список разрешённых к обновлению полей
        $fields = ['title', 'slug', 'icon', 'description', 'menu', 'menu_section_id', 'sort_order'];
        $data = $request->only($fields);

        // Валидация только реально пришедших полей
        $rules = [];
        if (array_key_exists('title', $data)) {
            $rules['title'] = 'required|string|max:255';
        }
        if (array_key_exists('slug', $data)) {
            $rules['slug'] = 'required|string|max:255|unique:admin_pages,slug,' . $id;
        }
        if (array_key_exists('icon', $data)) {
            $rules['icon'] = 'nullable|string|max:255';
        }
        if (array_key_exists('description', $data)) {
            $rules['description'] = 'nullable|string';
        }
        if (array_key_exists('menu', $data)) {
            $rules['menu'] = 'boolean';
        }
        if (array_key_exists('menu_section_id', $data)) {
            $rules['menu_section_id'] = 'nullable|exists:menu_sections,id';
        }
        if (array_key_exists('sort_order', $data)) {
            $rules['sort_order'] = 'nullable|integer|min:0';
        }

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Для sort_order: если поле пришло, но пустое, ставим 0
        if (array_key_exists('sort_order', $data) && ($data['sort_order'] === null || $data['sort_order'] === '' || (empty($data['sort_order']) && $data['sort_order'] !== 0))) {
            $data['sort_order'] = 0;
        }

        $page->update($data);
        $page->load(['menuSection' => function ($query) {
            $query->select('id', 'name');
        }]);

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
