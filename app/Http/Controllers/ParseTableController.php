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

            // Определяем кодировку из заголовков ответа
            $contentType = $response->header('Content-Type');
            $charset = 'UTF-8';
            if (preg_match('/charset=([^;]+)/i', $contentType, $matches)) {
                $charset = $matches[1];
            }

            // Получаем HTML и конвертируем в UTF-8
            $html = $response->body();
            if ($charset !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $charset);
            }

            $searchText = $request->search_text;

            // Сохраняем HTML в лог для анализа
            \Log::info('Page HTML: ' . $html);

            // Создаем DOM объект
            $dom = new DOMDocument();
            // Устанавливаем кодировку UTF-8
            $dom->encoding = 'UTF-8';
            // Загружаем HTML с игнорированием ошибок
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);

            $debug['dom_created'] = true;
            $debug['charset'] = $charset;

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

                // Ищем таблицу по точному классу
                $table = $xpath->query('//table[contains(@class, "ui") and contains(@class, "table") and contains(@class, "tbl-stat")]');

                if ($table->length > 0) {
                    \Log::info('Found table');
                    $targetTable = $table->item(0);

                    // Получаем заголовки
                    $headers = [];
                    $headerCells = $xpath->query('.//thead//th[contains(@class, "tablesorter-header")]//div[contains(@class, "tablesorter-header-inner")]', $targetTable);
                    foreach ($headerCells as $header) {
                        $headerText = trim($header->textContent);
                        if (!empty($headerText)) {
                            $headers[] = $headerText;
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

                            // Для ячейки с названием команды берем только текст, без ссылок и картинок
                            if ($cell->hasAttribute('class') && strpos($cell->getAttribute('class'), 'stats-team') !== false) {
                                $value = trim($cell->textContent);
                            }
                            // Для ячейки с формой берем только результат (В/Н/П)
                            else if ($cell->hasAttribute('class') && strpos($cell->getAttribute('class'), 'form-links') !== false) {
                                $formLinks = $xpath->query('.//span[contains(@class, "winner") or contains(@class, "looser")]', $cell);
                                $formValues = [];
                                foreach ($formLinks as $link) {
                                    if ($link->hasAttribute('class')) {
                                        if (strpos($link->getAttribute('class'), 'winner') !== false) {
                                            $formValues[] = 'В';
                                        } else if (strpos($link->getAttribute('class'), 'looser') !== false) {
                                            $formValues[] = 'П';
                                        }
                                    }
                                }
                                $value = implode('', $formValues);
                            }
                            // Для остальных ячеек берем текст напрямую
                            else {
                                $value = trim($cell->textContent);
                            }

                            // Если значение пустое, ставим #
                            if (empty($value)) {
                                $value = '#';
                            }

                            // Ограничиваем длину значения до 255 символов
                            $value = substr($value, 0, 255);

                            $rowData[] = $value;
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
                        // Ищем таблицу с нужным текстом в любой ячейке
                        foreach ($tables as $table) {
                            // Ищем текст во всех ячейках таблицы
                            $cells = $xpath->query('.//td | .//th', $table);
                            $found = false;

                            foreach ($cells as $cell) {
                                $cellText = trim($cell->textContent);
                                if (stripos($cellText, $searchText) !== false) {
                                    $targetTable = $table;
                                    $found = true;
                                    \Log::info('Found table with search text: ' . $searchText . ' in cell: ' . $cellText);
                                    break 2;
                                }
                            }

                            // Если не нашли в ячейках, проверяем в атрибутах
                            if (!$found) {
                                $elements = $xpath->query('.//*[@*[contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . strtolower($searchText) . '")]]', $table);
                                if ($elements->length > 0) {
                                    $targetTable = $table;
                                    \Log::info('Found table with search text in attributes');
                                    break;
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

                    // Анализируем содержимое каждого столбца
                    $validColumns = [];
                    $columnIndex = 0;
                    $colspanMap = []; // Карта для отслеживания объединенных ячеек

                    foreach ($headerCells as $index => $cell) {
                        if (count($headers) >= 20) break;

                        // Проверяем colspan
                        $colspan = $cell->getAttribute('colspan');
                        $colspan = $colspan ? intval($colspan) : 1;

                        $header = trim($cell->textContent);
                        if (empty($header)) {
                            $header = '#';
                        }

                        // Добавляем заголовок
                        if (!in_array($header, $headers)) {
                            $headers[] = $header;
                            $validColumns[] = $columnIndex;

                            // Если есть colspan, отмечаем следующие ячейки как объединенные
                            if ($colspan > 1) {
                                for ($i = 1; $i < $colspan; $i++) {
                                    $colspanMap[$columnIndex + $i] = $columnIndex;
                                }
                            }
                        }

                        $columnIndex += $colspan;
                    }

                    $allRows = $xpath->query('.//tr', $targetTable);
                    if ($allRows->length > 0) {
                        $startIndex = ($headerCells->length === 0) ? 0 : 1;
                        for ($i = $startIndex; $i < $allRows->length; $i++) {
                            $row = $allRows->item($i);
                            $rowData = [];
                            $cells = $xpath->query('.//td', $row);
                            $currentIndex = 0;
                            $mergedValues = [];
                            $lastMainIndex = -1;

                            foreach ($cells as $cellIndex => $cell) {
                                $value = trim($cell->textContent);

                                // Если это часть объединенной ячейки
                                if (isset($colspanMap[$currentIndex])) {
                                    $mainIndex = $colspanMap[$currentIndex];
                                    if (!isset($mergedValues[$mainIndex])) {
                                        $mergedValues[$mainIndex] = [];
                                    }
                                    if (!empty($value)) {
                                        $mergedValues[$mainIndex][] = $value;
                                    }
                                    $currentIndex++;
                                    continue;
                                }

                                // Проверяем colspan в ячейке данных
                                $colspan = $cell->getAttribute('colspan');
                                $colspan = $colspan ? intval($colspan) : 1;

                                // Если ячейка объединена, собираем значения
                                if ($colspan > 1) {
                                    $mergedValues[$currentIndex] = [];
                                    if (!empty($value)) {
                                        $mergedValues[$currentIndex][] = $value;
                                    }
                                    $lastMainIndex = $currentIndex;
                                    $currentIndex += $colspan;
                                } else {
                                    // Если предыдущая ячейка была объединенной, пропускаем текущую
                                    if ($lastMainIndex !== -1 && $currentIndex === $lastMainIndex + 1) {
                                        $currentIndex++;
                                        continue;
                                    }

                                    if (!empty($value)) {
                                        $mergedValues[$currentIndex] = [$value];
                                    }
                                    $currentIndex++;
                                }
                            }

                            // Объединяем значения и добавляем в результат
                            ksort($mergedValues); // Сортируем по индексам
                            foreach ($mergedValues as $index => $values) {
                                if (!empty($values)) {
                                    $rowData[] = implode(' ', $values);
                                } else {
                                    $rowData[] = '';
                                }
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
