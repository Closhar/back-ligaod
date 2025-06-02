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
            Log::info('Получен HTML', ['html_length' => strlen($html)]);

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
                $header = trim($cell->textContent);
                Log::info('Найден заголовок', ['header' => $header]);
                $headers[] = $header;
            }

            // Если нет th, пробуем взять первую строку
            if (empty($headers)) {
                $firstRow = $xpath->query('.//tr[1]/td', $table);
                foreach ($firstRow as $cell) {
                    $header = trim($cell->textContent);
                    Log::info('Найден заголовок из первой строки', ['header' => $header]);
                    $headers[] = $header;
                }
            }

            // Получаем данные
            $rows = [];
            $dataRows = $xpath->query('.//tr[position() > 1]', $table);
            foreach ($dataRows as $rowIndex => $row) {
                $rowData = [];
                $cells = $xpath->query('.//td', $row);
                foreach ($cells as $cellIndex => $cell) {
                    $value = trim($cell->textContent);
                    Log::info('Получено значение ячейки', [
                        'row' => $rowIndex + 1,
                        'cell' => $cellIndex + 1,
                        'value' => $value
                    ]);
                    $rowData[] = $value;
                }
                if (!empty($rowData)) {
                    Log::info('Добавлена строка', [
                        'row_index' => $rowIndex,
                        'data' => $rowData
                    ]);
                    $rows[] = $rowData;
                }
            }

            Log::info('Найдены строки таблицы', [
                'headers' => $headers,
                'rows_count' => count($rows),
                'all_rows' => $rows
            ]);

            // Создаем таблицу
            $tableModel = new ParseTable();
            $tableModel->title = 'Импортированная таблица ' . date('Y-m-d H:i:s');
            $tableModel->description = 'Импортировано из ' . $request->url;

            // Заполняем заголовки полей
            foreach ($headers as $index => $header) {
                $fieldName = 'field' . ($index + 1);
                if (property_exists($tableModel, $fieldName)) {
                    $tableModel->$fieldName = $header;
                }
            }

            $tableModel->save();
            Log::info('Создана таблица', [
                'table_id' => $tableModel->id,
                'title' => $tableModel->title,
                'headers' => $headers
            ]);

            // Сохраняем данные
            $savedRows = 0;
            foreach ($rows as $rowIndex => $row) {
                $content = new ParseTableContent();
                $content->table_id = $tableModel->id;

                // Заполняем поля данными
                foreach ($row as $index => $value) {
                    $fieldName = 'field' . ($index + 1);
                    Log::info('Заполняем поле', [
                        'row_index' => $rowIndex,
                        'field_name' => $fieldName,
                        'value' => $value
                    ]);
                    $content->$fieldName = $value;
                }

                try {
                    Log::info('Попытка сохранения строки', [
                        'table_id' => $tableModel->id,
                        'row_index' => $rowIndex,
                        'row_data' => $row,
                        'content_model' => $content->toArray()
                    ]);

                    $content->save();
                    $savedRows++;

                    Log::info('Строка успешно сохранена', [
                        'table_id' => $tableModel->id,
                        'content_id' => $content->id,
                        'row_index' => $rowIndex,
                        'saved_data' => $content->toArray()
                    ]);
                } catch (\Exception $e) {
                    Log::error('Ошибка при сохранении строки таблицы', [
                        'table_id' => $tableModel->id,
                        'row_index' => $rowIndex,
                        'row_data' => $row,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('Завершен импорт таблицы', [
                'table_id' => $tableModel->id,
                'total_rows' => count($rows),
                'saved_rows' => $savedRows
            ]);

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