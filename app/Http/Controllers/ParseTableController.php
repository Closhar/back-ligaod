<?php

namespace App\Http\Controllers;

use App\Models\ParseTable;
use App\Models\ParseTableContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;

class ParseTableController extends Controller
{
    /**
     * Получить список всех таблиц
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');

        $tables = ParseTable::orderBy($sort, $order)->get();

        return response()->json([
            'success' => true,
            'data' => $tables,
            'sort' => $sort,
            'order' => $order
        ]);
    }

    /**
     * Создать новую таблицу
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'field1' => 'nullable|string|max:255',
            'field2' => 'nullable|string|max:255',
            'field3' => 'nullable|string|max:255',
            'field4' => 'nullable|string|max:255',
            'field5' => 'nullable|string|max:255',
            'field6' => 'nullable|string|max:255',
            'field7' => 'nullable|string|max:255',
            'field8' => 'nullable|string|max:255',
            'field9' => 'nullable|string|max:255',
            'field10' => 'nullable|string|max:255',
            'field11' => 'nullable|string|max:255',
            'field12' => 'nullable|string|max:255',
            'field13' => 'nullable|string|max:255',
            'field14' => 'nullable|string|max:255',
            'field15' => 'nullable|string|max:255',
            'field16' => 'nullable|string|max:255',
            'field17' => 'nullable|string|max:255',
            'field18' => 'nullable|string|max:255',
            'field19' => 'nullable|string|max:255',
            'field20' => 'nullable|string|max:255',
        ]);

        $table = ParseTable::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $table
        ], 201);
    }

    /**
     * Обновить существующую таблицу
     */
    public function update(Request $request, ParseTable $table)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'field1' => 'nullable|string|max:255',
            'field2' => 'nullable|string|max:255',
            'field3' => 'nullable|string|max:255',
            'field4' => 'nullable|string|max:255',
            'field5' => 'nullable|string|max:255',
            'field6' => 'nullable|string|max:255',
            'field7' => 'nullable|string|max:255',
            'field8' => 'nullable|string|max:255',
            'field9' => 'nullable|string|max:255',
            'field10' => 'nullable|string|max:255',
            'field11' => 'nullable|string|max:255',
            'field12' => 'nullable|string|max:255',
            'field13' => 'nullable|string|max:255',
            'field14' => 'nullable|string|max:255',
            'field15' => 'nullable|string|max:255',
            'field16' => 'nullable|string|max:255',
            'field17' => 'nullable|string|max:255',
            'field18' => 'nullable|string|max:255',
            'field19' => 'nullable|string|max:255',
            'field20' => 'nullable|string|max:255',
        ]);

        $table->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $table
        ]);
    }

    /**
     * Удалить таблицу
     */
    public function destroy(ParseTable $table)
    {
        // Удаляем все связанные записи содержимого
        ParseTableContent::where('table_id', $table->id)->delete();

        // Удаляем саму таблицу
        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Таблица успешно удалена'
        ]);
    }

    /**
     * Парсинг таблицы с внешнего URL
     */
    public function parse(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'search_text' => 'nullable|string|max:255'
        ]);

        try {
            // Получаем HTML страницы
            $response = Http::get($request->url);
            if (!$response->successful()) {
                throw new \Exception('Не удалось получить доступ к странице');
            }

            $html = $response->body();
            $searchText = $request->search_text;

            // Создаем DOM объект
            $dom = new DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $xpath = new DOMXPath($dom);

            // Ищем все таблицы
            $tables = $xpath->query('//table');
            $targetTable = null;

            if ($tables->length === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Таблицы не найдены на странице'
                ], 404);
            }

            // Если search_text не указан, берем первую таблицу
            if (empty($searchText)) {
                $targetTable = $tables->item(0);
            } else {
                // Ищем таблицу с нужным заголовком
                foreach ($tables as $table) {
                    $headers = [];
                    $headerCells = $xpath->query('.//th', $table);
                    foreach ($headerCells as $cell) {
                        $headers[] = trim($cell->textContent);
                    }

                    // Если нет th, пробуем взять первую строку
                    if (empty($headers)) {
                        $firstRow = $xpath->query('.//tr[1]/td', $table);
                        foreach ($firstRow as $cell) {
                            $headers[] = trim($cell->textContent);
                        }
                    }

                    // Проверяем наличие искомого текста в заголовках
                    foreach ($headers as $header) {
                        if (stripos($header, $searchText) !== false) {
                            $targetTable = $table;
                            break 2;
                        }
                    }
                }

                if (!$targetTable) {
                    return response()->json([
                        'success' => false,
                        'message' => "Таблица с заголовком, содержащим '{$searchText}', не найдена"
                    ], 404);
                }
            }

            // Получаем заголовки из thead или первой строки
            $headers = [];
            $headerCells = $xpath->query('.//thead//th | .//thead//td', $targetTable);

            // Если заголовки не найдены в thead, пробуем взять из первой строки
            if ($headerCells->length === 0) {
                $headerCells = $xpath->query('.//tr[1]/th | .//tr[1]/td', $targetTable);
            }

            foreach ($headerCells as $index => $cell) {
                if (count($headers) >= 20) {
                    break;
                }

                $header = trim($cell->textContent);
                if (empty($header)) {
                    $header = '#';
                }

                if (!in_array($header, $headers)) {
                    $headers[] = $header;
                }
            }

            // Получаем данные из tbody, исключая первую строку если она была использована как заголовок
            $rows = [];

            // Сначала пробуем получить все строки таблицы
            $allRows = $xpath->query('.//tr', $targetTable);

            // Если есть строки, обрабатываем их
            if ($allRows->length > 0) {
                // Пропускаем первую строку, если она была использована как заголовок
                $startIndex = ($headerCells->length === 0) ? 0 : 1;

                for ($i = $startIndex; $i < $allRows->length; $i++) {
                    $row = $allRows->item($i);
                    $rowData = [];
                    $cells = $xpath->query('.//td', $row);

                    foreach ($cells as $cell) {
                        $rowData[] = trim($cell->textContent);
                    }

                    if (!empty($rowData)) {
                        $rows[] = $rowData;
                    }
                }
            }

            // Создаем таблицу
            $tableModel = new ParseTable();
            $tableModel->title = 'Импортированная таблица ' . date('Y-m-d H:i:s');
            $tableModel->description = 'Импортировано из ' . $request->url;

            // Заполняем заголовки полей
            foreach ($headers as $index => $header) {
                $fieldName = 'field' . ($index + 1);
                $tableModel->$fieldName = $header;
            }

            // Заполняем оставшиеся поля null
            for ($i = count($headers); $i < 20; $i++) {
                $fieldName = 'field' . ($i + 1);
                $tableModel->$fieldName = null;
            }

            $tableModel->save();

            // Сохраняем данные
            foreach ($rows as $row) {
                $content = new ParseTableContent();
                $content->table_id = $tableModel->id;

                foreach ($row as $colIndex => $value) {
                    if ($colIndex < 20) {
                        $fieldName = 'field' . ($colIndex + 1);
                        $content->$fieldName = $value;
                    }
                }

                $content->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Таблица успешно импортирована',
                'data' => [
                    'table' => $tableModel,
                    'rows_count' => count($rows)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при импорте таблицы: ' . $e->getMessage()
            ], 500);
        }
    }
}
