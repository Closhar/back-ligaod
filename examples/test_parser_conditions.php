<?php

/**
 * Тестовый скрипт для проверки условий парсинга
 * Запуск: php examples/test_parser_conditions.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Инициализация Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ParserTemplate;
use App\Services\ParserService;

echo "=== Тест условий парсинга ===\n\n";

// Получаем шаблон по ID (замените на реальный ID)
$templateId = 2; // Замените на ID вашего шаблона
$template = ParserTemplate::find($templateId);

if (!$template) {
    echo "Шаблон с ID $templateId не найден!\n";
    exit(1);
}

echo "Шаблон: {$template->name}\n";
echo "URL паттерн: {$template->url_pattern}\n";
echo "Условия: " . json_encode($template->conditions, JSON_UNESCAPED_UNICODE) . "\n\n";

// Проверяем поля шаблона
$fields = $template->fields;
echo "Поля для извлечения данных:\n";
if ($fields->count() > 0) {
    foreach ($fields as $field) {
        echo "- {$field->name} (селектор: {$field->selector}, тип: {$field->selector_type})\n";
        echo "  Целевая таблица: {$field->target_table}.{$field->target_field}\n";
        echo "  Стратегия обновления: {$field->update_strategy}\n";
        echo "  Обязательное: " . ($field->is_required ? 'Да' : 'Нет') . "\n\n";
    }
} else {
    echo "❌ Поля не настроены! Это причина пустых данных.\n\n";
}

// Тестируем URL
$testUrl = 'https://online.khl.ru/online/899314.html';

echo "Тестируем URL: $testUrl\n";

// Проверяем соответствие URL паттерну
if ($template->matchesUrl($testUrl)) {
    echo "✓ URL соответствует паттерну\n";
} else {
    echo "✗ URL НЕ соответствует паттерну\n";
    exit(1);
}

// Создаем экземпляр ParserService
$parserService = new ParserService();

// Тестируем шаблон
try {
    $result = $parserService->testTemplate($template, $testUrl);

    if ($result['success']) {
        echo "✓ Шаблон успешно обработал страницу\n";
        echo "Извлеченные данные: " . json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

        if (empty($result['data'])) {
            echo "\n⚠️  Данные пустые! Возможные причины:\n";
            echo "1. В шаблоне не настроены поля для извлечения\n";
            echo "2. Селекторы полей не находят элементы на странице\n";
            echo "3. HTML страницы изменился\n";
        }
    } else {
        echo "✗ Ошибка: {$result['error']}\n";

        if (isset($result['debug_info'])) {
            echo "\nОтладочная информация:\n";
            foreach ($result['debug_info'] as $debug) {
                echo "- Условие: " . json_encode($debug['condition'], JSON_UNESCAPED_UNICODE) . "\n";
                echo "  Извлеченное значение: '{$debug['extracted_value']}'\n";
                echo "  Прошло проверку: " . ($debug['passed'] ? 'Да' : 'Нет') . "\n\n";
            }
        }

        if (isset($result['html_preview'])) {
            echo "HTML превью (первые 1000 символов):\n";
            echo $result['html_preview'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Исключение: " . $e->getMessage() . "\n";
}

echo "\n=== Конец теста ===\n";
