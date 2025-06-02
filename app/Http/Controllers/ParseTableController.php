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
    public function index()
    {
        $tables = ParseTable::all();
        return response()->json([
            'success' => true,
            'data' => $tables
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
            'url' => 'required|url'
        ]);

        try {
            // Получаем HTML страницы
            $response = Http::get($request->url);
            if (!$response->successful()) {
                throw new \Exception('Не удалось получить доступ к странице');
            }

            $html = $response->body();

            // Создаем DOM объект
            $dom = new DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR);
            $xpath = new DOMXPath($dom);

            // Ищем таблицу
            $tables = $xpath->query('//table');

            if ($tables->length === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Таблица не найдена на странице'
                ], 404);
            }

            // Берем первую таблицу
            $table = $tables->item(0);

            // Получаем заголовки
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

            // Заполняем пустые заголовки значениями по умолчанию
            $defaultHeaders = [
                '№', 'Команда', 'Игры', 'Очки', 'В', 'Н', 'П', 'Мячи', 'Разница',
                'Форма', 'Последние матчи', 'Следующий матч', 'Стадион', 'Тренер',
                'Бюджет', 'Средний возраст', 'Легионеры', 'Молодые игроки',
                'Достижения', 'История'
            ];

            foreach ($headers as $index => $header) {
                if (empty($header) && isset($defaultHeaders[$index])) {
                    $headers[$index] = $defaultHeaders[$index];
                }
            }

            // Получаем данные
            $rows = [];
            $dataRows = $xpath->query('.//tr[position() > 1]', $table);
            foreach ($dataRows as $row) {
                $rowData = [];
                $cells = $xpath->query('.//td', $row);
                foreach ($cells as $cell) {
                    $rowData[] = trim($cell->textContent);
                }
                if (!empty($rowData)) {
                    $rows[] = $rowData;
                }
            }

            // Создаем таблицу
            $tableModel = new ParseTable();
            $tableModel->title = 'Импортированная таблица ' . date('Y-m-d H:i:s');
            $tableModel->description = 'Импортировано из ' . $request->url;

            // Заполняем заголовки полей в таблице parse_tables
            foreach ($headers as $index => $header) {
                $fieldName = 'field' . ($index + 1);
                if (property_exists($tableModel, $fieldName)) {
                    // Если заголовок пустой, используем значение по умолчанию
                    $tableModel->$fieldName = !empty($header) ? $header : $defaultHeaders[$index];
                }
            }

            // Заполняем оставшиеся поля пустыми значениями
            for ($i = count($headers); $i < 20; $i++) {
                $fieldName = 'field' . ($i + 1);
                if (property_exists($tableModel, $fieldName)) {
                    $tableModel->$fieldName = null;
                }
            }

            $tableModel->save();

            // Сохраняем данные
            $savedRows = 0;
            foreach ($rows as $row) {
                $content = new ParseTableContent();
                $content->table_id = $tableModel->id;

                // Заполняем поля данными
                foreach ($row as $index => $value) {
                    $fieldName = 'field' . ($index + 1);
                    $content->$fieldName = $value;
                }

                try {
                    $content->save();
                    $savedRows++;
                } catch (\Exception $e) {
                    Log::error('Ошибка при сохранении строки таблицы: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Таблица успешно импортирована',
                'data' => [
                    'table_id' => $tableModel->id,
                    'rows_count' => count($rows),
                    'saved_rows' => $savedRows
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка при парсинге таблицы: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при парсинге таблицы: ' . $e->getMessage()
            ], 500);
        }
    }
}
