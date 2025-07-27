<?php
// Простой тест доступности маршрута

$url = 'http://p.sportrep.ru/api/people/35/statistics/season/1/competition/111';

echo "Тестирование маршрута: $url\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ Ошибка при запросе\n";
    $error = error_get_last();
    echo "Ошибка: " . $error['message'] . "\n";
} else {
    echo "✅ Получен ответ\n";
    $data = json_decode($response, true);

    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ API работает\n";
        echo "Сезонов: " . count($data['data']['seasons']) . "\n";
        echo "Соревнований: " . count($data['data']['competitions']) . "\n";
    } else {
        echo "❌ API вернул ошибку\n";
        echo "Ответ: " . $response . "\n";
    }
}

echo "\nТестирование завершено.\n";
?>
