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
        $debug = [
            'message' => 'ParseTableController::parse called',
            'request_url' => $request->url,
            'request_data' => $request->all()
        ];

        $request->validate([
            'url' => 'required|url',
            'search_text' => 'nullable|string|max:255'
        ]);

        try {
            // Получаем HTML страницы
            $response = Http::get($request->url);
            if (!$response->successful()) {
                $debug['error'] = 'Failed to get page: ' . $request->url;
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось получить доступ к странице',
                    'debug' => $debug
                ], 500);
            }

            $debug['page_content_length'] = strlen($response->body());
            $html = $response->body();
            $searchText = $request->search_text;

            // Создаем DOM объект
            $dom = new DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $xpath = new DOMXPath($dom);

            $debug['dom_created'] = true;

            // Пробуем сначала найти обычные таблицы
            $tables = $xpath->query('//table');
            $targetTable = null;
            $isListFormat = false;

            $debug['tables_found'] = $tables->length;

            // Если таблицы не найдены, пробуем найти список
            if ($tables->length === 0) {
                $lists = $xpath->query('//ul[contains(@class, "table") or contains(@class, "list")]');
                $debug['lists_found'] = $lists->length;
                if ($lists->length > 0) {
                    $targetTable = $lists->item(0);
                    $isListFormat = true;
                }
            } else {
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
                }
            }

            if (!$targetTable) {
                $debug['error'] = 'Table not found';
                return response()->json([
                    'success' => false,
                    'message' => "Таблица или список не найдены на странице",
                    'debug' => $debug
                ], 404);
            }

            $headers = [];
            $rows = [];

            if ($isListFormat) {
                // Специальная обработка для yflrussia.ru
                if (strpos($request->url, 'yflrussia.ru') !== false) {
                    \Log::info('Processing yflrussia.ru table');

                    // Ищем таблицу с нужными заголовками
                    $tableFound = false;

                    // Получаем все div-элементы, которые могут быть таблицами
                    $tableDivs = $xpath->query('//div[contains(@class, "table")]');

                    $debug['tables_found'] = $tableDivs->length;
                    $debug['tables_info'] = [];

                    \Log::info('Found ' . $tableDivs->length . ' potential tables');

                    // Сначала ищем таблицу по специфическому классу
                    $tournamentTable = $xpath->query('//div[contains(@class, "tournament-table")]');
                    if ($tournamentTable->length > 0) {
                        \Log::info('Found tournament-table class');
                        $targetTable = $tournamentTable->item(0);
                        $isListFormat = true;
                        $tableFound = true;
                    } else {
                        \Log::info('No tournament-table class found, searching by content');

                        foreach ($tableDivs as $index => $tableDiv) {
                            $tableInfo = [
                                'index' => $index,
                                'class' => $tableDiv->getAttribute('class'),
                                'headers' => [],
                                'first_row' => [],
                                'row_count' => 0
                            ];

                            \Log::info('Analyzing table ' . $index . ' with class: ' . $tableInfo['class']);

                            // Получаем все заголовки таблицы
                            $headerItems = $xpath->query('.//div[contains(@class, "thead")]//div[contains(@class, "tr")]//div[contains(@class, "th")]', $tableDiv);
                            foreach ($headerItems as $header) {
                                $headerText = trim($header->textContent);
                                if (!empty($headerText)) {
                                    $tableInfo['headers'][] = $headerText;
                                }
                            }

                            \Log::info('Table ' . $index . ' headers: ' . implode(', ', $tableInfo['headers']));

                            // Получаем первую строку данных
                            $firstRow = $xpath->query('.//div[contains(@class, "tbody")]//div[contains(@class, "tr")][1]//div[contains(@class, "td")]', $tableDiv);
                            foreach ($firstRow as $cell) {
                                $value = trim($cell->textContent);
                                if (!empty($value)) {
                                    $tableInfo['first_row'][] = $value;
                                }
                            }

                            \Log::info('Table ' . $index . ' first row: ' . implode(', ', $tableInfo['first_row']));

                            // Считаем количество строк
                            $rows = $xpath->query('.//div[contains(@class, "tbody")]//div[contains(@class, "tr")]', $tableDiv);
                            $tableInfo['row_count'] = $rows->length;

                            \Log::info('Table ' . $index . ' row count: ' . $tableInfo['row_count']);

                            $debug['tables_info'][] = $tableInfo;

                            // Проверяем, является ли это нужной нам таблицей
                            $isFullTable = false;

                            // Проверяем наличие всех необходимых заголовков
                            $requiredHeaders = ['МЗ - МП', 'Форма', 'В', 'Н', 'П'];
                            $hasAllRequired = true;
                            foreach ($requiredHeaders as $required) {
                                $found = false;
                                foreach ($tableInfo['headers'] as $header) {
                                    if (stripos($header, $required) !== false) {
                                        $found = true;
                                        break;
                                    }
                                }
                                if (!$found) {
                                    $hasAllRequired = false;
                                    break;
                                }
                            }

                            \Log::info('Table ' . $index . ' has all required headers: ' . ($hasAllRequired ? 'yes' : 'no'));

                            // Проверяем наличие данных в первой строке
                            $hasData = false;
                            foreach ($tableInfo['first_row'] as $value) {
                                if (preg_match('/\d+ - \d+/', $value)) {
                                    $hasData = true;
                                    break;
                                }
                            }

                            \Log::info('Table ' . $index . ' has data in first row: ' . ($hasData ? 'yes' : 'no'));

                            if ($hasAllRequired && $hasData && count($tableInfo['headers']) >= 9) {
                                $targetTable = $tableDiv;
                                $isListFormat = true;
                                $tableFound = true;
                                $debug['selected_table_index'] = $index;
                                $debug['selected_table_info'] = $tableInfo;
                                \Log::info('Selected table ' . $index);
                                break;
                            }
                        }
                    }
                } else {
                    // Стандартная обработка для других сайтов
                    $headerItems = $xpath->query('.//li[contains(@class, "header") or contains(@class, "title") or contains(@class, "thead")]', $targetTable);
                    if ($headerItems->length > 0) {
                        $headerDivs = $xpath->query('.//div[not(contains(@class, "form"))]', $headerItems->item(0));
                        foreach ($headerDivs as $div) {
                            if (count($headers) >= 20) break;
                            $header = trim($div->textContent);
                            if (!empty($header) && !in_array($header, $headers)) {
                                $headers[] = $header;
                            }
                        }
                    }

                    // Получаем строки данных
                    $listItems = $xpath->query('.//li[not(contains(@class, "header")) and not(contains(@class, "title")) and not(contains(@class, "thead"))]', $targetTable);
                    foreach ($listItems as $item) {
                        $rowData = [];
                        $divs = $xpath->query('.//div[not(contains(@class, "form"))]', $item);
                        foreach ($divs as $div) {
                            if (count($rowData) >= 20) break;
                            $value = trim($div->textContent);
                            $value = preg_replace('/\s+/', ' ', $value);
                            $rowData[] = $value;
                        }
                        if (!empty($rowData)) {
                            $rows[] = $rowData;
                        }
                    }
                }

                // Если заголовки не найдены, пробуем найти их в первой строке
                if (empty($headers) && !empty($rows)) {
                    $firstRow = $rows[0];
                    $headers = array_map(function($index) {
                        return 'Поле ' . ($index + 1);
                    }, array_keys($firstRow));
                }
            } else {
                // Существующая логика для обычных таблиц
                $headerCells = $xpath->query('.//thead//th | .//thead//td', $targetTable);
                if ($headerCells->length === 0) {
                    $headerCells = $xpath->query('.//tr[1]/th | .//tr[1]/td', $targetTable);
                }

                foreach ($headerCells as $index => $cell) {
                    if (count($headers) >= 20) break;
                    $header = trim($cell->textContent);
                    if (empty($header)) {
                        $header = '#';
                    }
                    if (!in_array($header, $headers)) {
                        $headers[] = $header;
                    }
                }

                $allRows = $xpath->query('.//tr', $targetTable);
                if ($allRows->length > 0) {
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
                ],
                'debug' => $debug
            ]);

        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
            $debug['error_trace'] = $e->getTraceAsString();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при импорте таблицы: ' . $e->getMessage(),
                'debug' => $debug
            ], 500);
        }
    }
}
