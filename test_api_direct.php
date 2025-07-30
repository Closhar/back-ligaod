<?php

// Прямой тест API endpoint
$apiUrl = 'http://127.0.0.1:8000/api/v1/events/1?include=lineups';

echo "Тестирование API endpoint: $apiUrl\n";
echo "=====================================\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";

if ($response) {
    $data = json_decode($response, true);

    if (is_array($data) && count($data) > 0) {
        $event = $data[0] ?? $data;

        echo "Event ID: " . ($event['id'] ?? 'N/A') . "\n";
        echo "Report field: " . ($event['report'] ?? 'NULL') . "\n";
        echo "Report exists: " . (isset($event['report']) ? 'YES' : 'NO') . "\n";
        echo "Report is null: " . (is_null($event['report']) ? 'YES' : 'NO') . "\n";
        echo "Report length: " . (strlen($event['report'] ?? '') ?: 0) . "\n";

        if (isset($event['report']) && !is_null($event['report'])) {
            echo "\nПервые 200 символов report:\n";
            echo substr($event['report'], 0, 200) . "...\n";
        }
    } else {
        echo "Неверный формат ответа\n";
        echo "Raw response: " . substr($response, 0, 500) . "...\n";
    }
} else {
    echo "Ошибка получения ответа\n";
}
?>
