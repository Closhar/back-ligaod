<?php

echo "🔍 Тестирование API через curl\n";
echo "=============================\n\n";

// Получаем данные для теста
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();

    if (!$city || !$sport || !$gender) {
        echo "❌ Недостаточно данных\n";
        exit(1);
    }

    $testData = [
        'title' => 'Curl Тест ' . time(),
        'title_short' => 'КТ',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];

    echo "📝 Тестовые данные:\n";
    print_r($testData);
    echo "\n";

    // Формируем JSON данные
    $jsonData = json_encode($testData);

    // URL для тестирования
    $url = 'https://p.sportrep.ru/api/clubs';

    echo "🚀 Отправляем curl запрос к: {$url}\n";
    echo "📦 Данные: {$jsonData}\n\n";

    // Выполняем curl запрос
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

    // Получаем verbose output
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // Показываем verbose информацию
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);

    echo "📊 Результат curl:\n";
    echo "HTTP Code: {$httpCode}\n";
    echo "Response: " . substr($response, 0, 500) . "\n";

    if ($error) {
        echo "❌ Curl Error: {$error}\n";
    }

    echo "\n📋 Verbose Log:\n";
    echo $verboseLog . "\n";

    curl_close($ch);

    if ($httpCode === 201) {
        echo "✅ API запрос через curl успешен!\n";

        // Пытаемся удалить созданный клуб
        $responseData = json_decode($response, true);
        if (isset($responseData['id'])) {
            echo "🗑️ Удаляем тестовый клуб с ID: {$responseData['id']}\n";

            // Удаляем через модель
            $club = \App\Models\Club::find($responseData['id']);
            if ($club) {
                $club->delete();
                echo "✅ Тестовый клуб удален\n";
            }
        }
    } else {
        echo "❌ API запрос через curl завершился с ошибкой\n";
        echo "HTTP Code: {$httpCode}\n";
        echo "Response: {$response}\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Тестирование curl завершено\n";
