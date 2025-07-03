<?php
/**
 * Тестовый скрипт для проверки асинхронных селектов
 * Проверяет работу API endpoints с параметрами type=async и поиском
 */

require_once 'vendor/autoload.php';

// Настройки
$baseUrl = 'http://localhost:8000/api';
$endpoints = [
    'clubs' => '/people/clubs',
    'sports' => '/people/sports',
    'positions' => '/people/positions',
    'ampluas' => '/people/ampluas'
];

echo "=== Тестирование асинхронных селектов ===\n\n";

foreach ($endpoints as $name => $endpoint) {
    echo "--- Тестирование {$name} ---\n";

    // Тест 1: Без параметров (должен вернуть первые 10 записей)
    echo "1. Запрос без параметров:\n";
    $url = $baseUrl . $endpoint;
    $response = makeRequest($url);
    echo "   Статус: " . $response['status'] . "\n";
    echo "   Количество записей: " . count($response['data'] ?? []) . "\n";
    if (!empty($response['data'])) {
        echo "   Первая запись: " . json_encode($response['data'][0], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";

    // Тест 2: С поиском
    echo "2. Запрос с поиском (q=фут):\n";
    $url = $baseUrl . $endpoint . '?type=async&q=фут&limit=5';
    $response = makeRequest($url);
    echo "   Статус: " . $response['status'] . "\n";
    echo "   Количество записей: " . count($response['data'] ?? []) . "\n";
    if (!empty($response['data'])) {
        echo "   Найденные записи:\n";
        foreach ($response['data'] as $item) {
            $name = $item['name'] ?? $item['title'] ?? 'N/A';
            echo "     - ID: {$item['id']}, Название: {$name}\n";
        }
    }
    echo "\n";

    // Тест 3: С лимитом
    echo "3. Запрос с лимитом (limit=3):\n";
    $url = $baseUrl . $endpoint . '?type=async&limit=3';
    $response = makeRequest($url);
    echo "   Статус: " . $response['status'] . "\n";
    echo "   Количество записей: " . count($response['data'] ?? []) . "\n";
    echo "\n";
}

echo "=== Проверка структуры данных ===\n";
foreach ($endpoints as $name => $endpoint) {
    echo "\n--- Структура данных для {$name} ---\n";
    $url = $baseUrl . $endpoint . '?type=async&limit=1';
    $response = makeRequest($url);

    if (!empty($response['data'])) {
        $item = $response['data'][0];
        echo "Обязательные поля:\n";
        echo "  - id: " . (isset($item['id']) ? '✓' : '✗') . "\n";
        echo "  - name: " . (isset($item['name']) ? '✓' : '✗') . "\n";
        echo "  - title: " . (isset($item['title']) ? '✓' : '✗') . "\n";

        if (isset($item['name'])) {
            echo "  Поле 'name' содержит: {$item['name']}\n";
        }
    }
}

function makeRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['status' => 'ERROR', 'data' => null, 'error' => 'CURL error'];
    }

    $data = json_decode($response, true);
    return [
        'status' => $httpCode,
        'data' => $data['data'] ?? null,
        'success' => $data['success'] ?? false,
        'message' => $data['message'] ?? null
    ];
}

echo "\n=== Тестирование завершено ===\n";
?>
