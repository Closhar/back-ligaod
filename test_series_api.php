<?php
/**
 * Тестовый файл для проверки API серий
 * Запуск: php test_series_api.php
 */

// URL API
$apiUrl = 'http://localhost/api/v1/events';

// Тест 1: Получение событий серии с фильтром по региону (старый способ)
echo "=== Тест 1: События серии с фильтром по региону ===\n";
$url1 = $apiUrl . '?series_id=1&per_page=10&show=4';
$response1 = file_get_contents($url1);
$data1 = json_decode($response1, true);
echo "Количество событий: " . count($data1['data']['data']) . "\n";
echo "Первые 3 события:\n";
foreach (array_slice($data1['data']['data'], 0, 3) as $event) {
    echo "- {$event['date_formatted']} {$event['event_name_top']} ({$event['region']['title']})\n";
}

echo "\n";

// Тест 2: Получение событий серии без фильтра по региону (новый способ)
echo "=== Тест 2: События серии без фильтра по региону ===\n";
$url2 = $apiUrl . '?series_id=1&per_page=10&show=4&all_regions=1';
$response2 = file_get_contents($url2);
$data2 = json_decode($response2, true);
echo "Количество событий: " . count($data2['data']['data']) . "\n";
echo "Первые 3 события:\n";
foreach (array_slice($data2['data']['data'], 0, 3) as $event) {
    echo "- {$event['date_formatted']} {$event['event_name_top']} ({$event['region']['title']})\n";
}

echo "\n";

// Тест 3: Сравнение результатов
echo "=== Тест 3: Сравнение результатов ===\n";
$count1 = count($data1['data']['data']);
$count2 = count($data2['data']['data']);
echo "Событий с фильтром региона: $count1\n";
echo "Событий без фильтра региона: $count2\n";
echo "Разница: " . ($count2 - $count1) . "\n";

if ($count2 > $count1) {
    echo "✅ Успех: Без фильтра региона найдено больше событий\n";
} else {
    echo "❌ Проблема: Фильтр региона не влияет на результат\n";
}
