<?php
// Простой тест API для проверки статистики игрока

// URL API
$apiUrl = 'http://p.sportrep.loc/api';

// ID игрока для тестирования
$playerId = 1;
$seasonId = 1;
$competitionId = 1;

echo "Тестирование API статистики игрока\n";
echo "===================================\n\n";

// 1. Тест получения сезонов
echo "1. Получение сезонов:\n";
$url = "$apiUrl/people/$playerId/statistics/seasons";
echo "URL: $url\n";

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data && isset($data['success']) && $data['success']) {
    echo "✓ Успешно получены сезоны\n";
    echo "Сезонов: " . count($data['data']['seasons']) . "\n";
    echo "Соревнований: " . count($data['data']['competitions']) . "\n";

    // Показываем первые несколько сезонов
    if (count($data['data']['seasons']) > 0) {
        echo "Первые сезоны:\n";
        foreach (array_slice($data['data']['seasons'], 0, 3) as $season) {
            echo "  - ID: {$season['id']}, Название: {$season['title']}\n";
        }
    }

    // Показываем первые несколько соревнований
    if (count($data['data']['competitions']) > 0) {
        echo "Первые соревнования:\n";
        foreach (array_slice($data['data']['competitions'], 0, 3) as $competition) {
            echo "  - ID: {$competition['id']}, Название: {$competition['title']}\n";
        }
    }
} else {
    echo "✗ Ошибка получения сезонов\n";
    echo "Ответ: " . $response . "\n";
}

echo "\n";

// 2. Тест комбинированного endpoint
echo "2. Тест комбинированного endpoint:\n";
$url = "$apiUrl/people/$playerId/statistics/season/$seasonId/competition/$competitionId";
echo "URL: $url\n";

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data && isset($data['success']) && $data['success']) {
    echo "✓ Успешно получена статистика\n";
    echo "Матчей: " . ($data['data']['total_matches'] ?? 0) . "\n";
    echo "Показателей: " . count($data['data']['statistics'] ?? []) . "\n";

    if (isset($data['data']['statistics'])) {
        echo "Статистика:\n";
        foreach ($data['data']['statistics'] as $key => $value) {
            echo "  $key: $value\n";
        }
    }
} else {
    echo "✗ Ошибка получения статистики\n";
    echo "Ответ: " . $response . "\n";
}

echo "\nТестирование завершено.\n";
?>
