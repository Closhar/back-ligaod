<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\ParserTemplate;
use App\Models\ParserField;

// Инициализируем Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Тестирование системы умного парсинга ===\n\n";

try {
    // Получаем шаблон парсера
    $template = ParserTemplate::with('fields')->find(2);
    
    if (!$template) {
        echo "❌ Шаблон с ID 2 не найден\n";
        exit(1);
    }
    
    echo "✅ Шаблон найден: {$template->name}\n";
    echo "📊 Количество полей: " . count($template->fields) . "\n\n";
    
    // Проверяем поля с настройками умного парсинга
    $smartFields = $template->fields->filter(function($field) {
        return !empty($field->search_phrase) || !empty($field->search_context);
    });
    
    if ($smartFields->isEmpty()) {
        echo "⚠️  Поля с настройками умного парсинга не найдены\n";
        echo "   Запустите SQL скрипт: examples/update_parser_fields_smart_parsing.sql\n";
        exit(1);
    }
    
    echo "🎯 Найдено полей с умным парсингом: " . $smartFields->count() . "\n\n";
    
    // Показываем настройки каждого поля
    foreach ($smartFields as $field) {
        echo "📝 Поле: {$field->name}\n";
        echo "   Контекст поиска: " . ($field->search_context ?: 'не указан') . "\n";
        echo "   Поисковая фраза: " . ($field->search_phrase ?: 'не указана') . "\n";
        echo "   Разделитель: " . ($field->search_phrase ?: 'не указан') . "\n";
        echo "   Формат результата: " . ($field->result_format ?: 'стандартный') . "\n";
        
        if ($field->team_identification) {
            echo "   Идентификация команд:\n";
            echo "     - Поисковая фраза: " . ($field->team_identification['search_phrase'] ?? 'не указана') . "\n";
            echo "     - Разделитель команд: " . ($field->team_identification['team_separator'] ?? 'не указан') . "\n";
        }
        
        echo "   Целевая таблица: " . ($field->target_table ?: 'не указана') . "\n";
        echo "   Целевое поле: " . ($field->target_field ?: 'не указано') . "\n";
        echo "\n";
    }
    
    // Тестируем парсинг на примере HTML
    echo "🧪 Тестирование парсинга...\n\n";
    
    $testHtml = '
    <html>
    <body>
        <h1>Игра № 44 21 авг 2025: МХК Крылья Советов-ХК Капитан</h1>
        <div>Статистика матча:</div>
        <div>Броски: 59-50</div>
        <div>Голы: 4-3</div>
        <div>Вбрасывания: 23-23</div>
        <div>матч завершен 4 – 3 от буллиты</div>
        <div>51:20 Изменение счета: 4—3 (МХК Крылья Советов).47 Башлыков Егор</div>
    </body>
    </html>';
    
    echo "📄 Тестовый HTML:\n";
    echo substr($testHtml, 0, 200) . "...\n\n";
    
    // Тестируем каждое поле
    foreach ($smartFields as $field) {
        echo "🔍 Тестирование поля '{$field->name}':\n";
        
        try {
            $value = $field->extractValue($testHtml);
            
            if ($value === null) {
                echo "   ❌ Значение не извлечено\n";
            } else {
                echo "   ✅ Извлечено: " . (is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo "✅ Тестирование завершено!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📚 Стек вызовов:\n" . $e->getTraceAsString() . "\n";
}
