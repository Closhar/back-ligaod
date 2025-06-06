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

            // Сохраняем HTML в лог для анализа
            \Log::info('Page HTML: ' . $html);

            // Создаем DOM объект
            $dom = new DOMDocument();
            // Устанавливаем кодировку UTF-8
            $dom->encoding = 'UTF-8';
            // Добавляем мета-тег с кодировкой
            $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
            // Загружаем HTML с игнорированием ошибок
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);

            $debug['dom_created'] = true;

            // Специальная обработка для yflrussia.ru
            if (strpos($request->url, 'yflrussia.ru') !== false) {
                \Log::info('Processing yflrussia.ru table');

                // Ищем таблицу по точному классу
                $tableDiv = $xpath->query('//div[contains(@class, "custom-table") and contains(@class, "custom-table-table")]');

                if ($tableDiv->length > 0) {
                    \Log::info('Found custom-table');
                    $targetTable = $tableDiv->item(0);
                    $isListFormat = true;

                    // Получаем заголовки
                    $headers = [];
                    $headerDivs = $xpath->query('.//div[contains(@class, "custom-table__head")]//div[contains(@class, "custom-table__content")]', $targetTable);
                    foreach ($headerDivs as $header) {
                        $headerText = trim($header->textContent);
                        if (!empty($headerText)) {
                            $headers[] = $headerText;
                        }
                    }

                    \Log::info('Headers found: ' . implode(', ', $headers));

                    // Получаем строки данных
                    $rows = [];
                    $rowItems = $xpath->query('.//li[contains(@class, "custom-table__line")]', $targetTable);

                    foreach ($rowItems as $rowIndex => $row) {
                        $rowData = [];

                        // Получаем номер
                        $numberCell = $xpath->query('.//div[contains(@class, "custom-table__number")]//div[contains(@class, "custom-table__number-wrapper")]', $row);
                        if ($numberCell->length > 0) {
                            $rowData[] = trim($numberCell->item(0)->textContent);
                        }

                        // Получаем название команды
                        $teamCell = $xpath->query('.//div[contains(@class, "custom-table__team-name")]', $row);
                        if ($teamCell->length > 0) {
                            $rowData[] = trim($teamCell->item(0)->textContent);
                        }

                        // Получаем все ячейки с данными в правильном порядке
                        $cellData = [];

                        // Получаем количество игр
                        $gamesCell = $xpath->query('.//div[contains(@class, "custom-table__var")][1]//div[contains(@class, "custom-table__content")]', $row);
                        if ($gamesCell->length > 0) {
                            $cellData[] = trim($gamesCell->item(0)->textContent);
                        }

                        // Получаем выигрыши
                        $winsCell = $xpath->query('.//div[contains(@class, "custom-table__var")][2]//div[contains(@class, "custom-table__content")]', $row);
                        if ($winsCell->length > 0) {
                            $cellData[] = trim($winsCell->item(0)->textContent);
                        }

                        // Получаем ничьи
                        $drawsCell = $xpath->query('.//div[contains(@class, "custom-table__var")][3]//div[contains(@class, "custom-table__content")]', $row);
                        if ($drawsCell->length > 0) {
                            $cellData[] = trim($drawsCell->item(0)->textContent);
                        }

                        // Получаем поражения
                        $lossesCell = $xpath->query('.//div[contains(@class, "custom-table__var")][4]//div[contains(@class, "custom-table__content")]', $row);
                        if ($lossesCell->length > 0) {
                            $cellData[] = trim($lossesCell->item(0)->textContent);
                        }

                        // Получаем забитые/пропущенные
                        $goalsCell = $xpath->query('.//div[contains(@class, "custom-table__var-long")]//div[contains(@class, "custom-table__content")]', $row);
                        if ($goalsCell->length > 0) {
                            $cellData[] = trim($goalsCell->item(0)->textContent);
                        }

                        // Получаем очки
                        $pointsCell = $xpath->query('.//div[contains(@class, "custom-table__score")]//div[contains(@class, "custom-table__content")]', $row);
                        if ($pointsCell->length > 0) {
                            $cellData[] = trim($pointsCell->item(0)->textContent);
                        }

                        // Добавляем все полученные данные
                        $rowData = array_merge($rowData, $cellData);

                        // Получаем форму (последние 5 матчей)
                        $formCell = $xpath->query('.//ul[contains(@class, "progress")]//span[contains(@class, "progress__text")]', $row);
                        $form = [];
                        foreach ($formCell as $formItem) {
                            $form[] = trim($formItem->textContent);
                        }
                        if (!empty($form)) {
                            $rowData[] = implode(' ', $form);
                        }

                        if (!empty($rowData)) {
                            $rows[] = $rowData;
                            \Log::info('Row ' . $rowIndex . ': ' . implode(', ', $rowData));
                        }
                    }

                    $debug['rows_parsed'] = count($rows);
                    if (!empty($rows)) {
                        $debug['first_row'] = $rows[0];
                    }
                } else {
                    \Log::info('No custom-table found');
                    return response()->json([
                        'success' => false,
                        'message' => "Турнирная таблица не найдена на странице",
                        'debug' => $debug
                    ], 404);
                }
            }
            // Специальная обработка для r-hockey.ru
            else if (strpos($request->url, 'r-hockey.ru') !== false) {
                \Log::info('Processing r-hockey.ru table');

                // Ищем таблицу по точному классу и атрибутам
                $table = $xpath->query('//table[contains(@class, "ui") and contains(@class, "table") and contains(@class, "tbl-stat")]');

                if ($table->length > 0) {
                    \Log::info('Found table');
                    $targetTable = $table->item(0);

                    // Получаем заголовки
                    $headers = [];
                    $headerCells = $xpath->query('.//thead//th[contains(@class, "tablesorter-header")]', $targetTable);
                    foreach ($headerCells as $header) {
                        $headerText = trim($header->textContent);
                        // Пропускаем пустые заголовки и лишние элементы
                        if (!empty($headerText) && $headerText !== 'Действия') {
                            $headers[] = mb_convert_encoding($headerText, 'UTF-8', 'auto');
                        }
                    }

                    // Если заголовки не найдены, используем стандартные
                    if (empty($headers)) {
                        $headers = ['#', 'Команда', 'И', 'В', 'ВО+ВБ', 'Н', 'ПО+ПБ', 'П', 'Ш', 'О', 'О%', 'Форма'];
                    }

                    \Log::info('Headers found: ' . implode(', ', $headers));

                    // Получаем строки данных
                    $rows = [];
                    $rowItems = $xpath->query('.//tbody//tr[contains(@class, "stats-row")]', $targetTable);

                    foreach ($rowItems as $rowIndex => $row) {
                        $rowData = [];

                        // Получаем все ячейки в строке
                        $cells = $xpath->query('.//td', $row);

                        foreach ($cells as $cellIndex => $cell) {
                            $value = '';

                            // Для ячейки с названием команды берем текст из ссылки
                            if ($cell->hasAttribute('class') && strpos($cell->getAttribute('class'), 'stats-team') !== false) {
                                $teamLink = $xpath->query('.//a', $cell);
                                if ($teamLink->length > 0) {
                                    $value = trim($teamLink->item(0)->textContent);
                                }
                            }
                            // Для ячейки с формой берем все ссылки
                            else if ($cell->hasAttribute('class') && strpos($cell->getAttribute('class'), 'form-links') !== false) {
                                $formLinks = $xpath->query('.//a', $cell);
                                $formValues = [];
                                foreach ($formLinks as $link) {
                                    $formValues[] = trim($link->getAttribute('data-tooltip'));
                                }
                                $value = implode(', ', $formValues);
                            }
                            // Для остальных ячеек берем текст напрямую
                            else {
                                $value = trim($cell->textContent);
                            }

                            // Конвертируем значение в UTF-8
                            $value = mb_convert_encoding($value, 'UTF-8', 'auto');

                            // Ограничиваем длину значения до 255 символов
                            $value = substr($value, 0, 255);

                            // Добавляем значение в массив, если оно не пустое
                            if (!empty($value)) {
                                $rowData[] = $value;
                            }
                        }

                        if (!empty($rowData)) {
                            $rows[] = $rowData;
                            \Log::info('Row ' . $rowIndex . ': ' . implode(', ', $rowData));
                        }
                    }

                    $debug['rows_parsed'] = count($rows);
                    if (!empty($rows)) {
                        $debug['first_row'] = $rows[0];
                    }

                    // Добавляем отладочную информацию
                    $debug['table_html'] = $dom->saveHTML($targetTable);
                    $debug['headers_count'] = count($headers);
                    $debug['rows_count'] = count($rows);
                } else {
                    \Log::info('No table found');
                    return response()->json([
                        'success' => false,
                        'message' => "Турнирная таблица не найдена на странице",
                        'debug' => $debug
                    ], 404);
                }
            } else {
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

                // Если заголовки не найдены, пробуем найти их в первой строке
                if (empty($headers) && !empty($rows)) {
                    $firstRow = $rows[0];
                    $headers = array_map(function($index) {
                        return 'Поле ' . ($index + 1);
                    }, array_keys($firstRow));
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
