<?php

echo "🔍 Тестирование реального HTTP запроса\n";
echo "=====================================\n\n";

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
        'title' => 'Real HTTP Test ' . time(),
        'title_short' => 'RHT',
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

    // URL для тестирования (как с фронтенда)
    $url = 'https://p.sportrep.ru/api/clubs';

    echo "🚀 Отправляем HTTP запрос как с фронтенда:\n";
    echo "URL: {$url}\n";
    echo "Method: POST\n";
    echo "Content-Type: application/json\n";
    echo "Data: {$jsonData}\n\n";

    // Выполняем HTTP запрос с заголовками как с фронтенда
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Origin: https://crm.sporterp.ru',
        'Referer: https://crm.sporterp.ru/',
        'X-Requested-With: XMLHttpRequest'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

    // Получаем verbose output
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);

    // Показываем verbose информацию
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);

    echo "📊 Результат HTTP запроса:\n";
    echo "HTTP Code: {$httpCode}\n";
    echo "Content Type: " . $info['content_type'] . "\n";
    echo "Response Size: " . strlen($response) . " bytes\n";
    echo "Total Time: " . $info['total_time'] . " seconds\n";
    echo "Response: " . substr($response, 0, 1000) . "\n";

    if ($error) {
        echo "❌ Curl Error: {$error}\n";
    }

    echo "\n📋 Verbose Log:\n";
    echo $verboseLog . "\n";

    curl_close($ch);

    if ($httpCode === 201) {
        echo "✅ HTTP запрос успешен!\n";

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
        echo "❌ HTTP запрос завершился с ошибкой\n";
        echo "HTTP Code: {$httpCode}\n";
        echo "Response: {$response}\n";

        // Анализируем ошибку
        if ($httpCode === 500) {
            echo "\n🔍 Анализ ошибки 500:\n";
            echo "- Возможная проблема с CORS\n";
            echo "- Возможная проблема с middleware\n";
            echo "- Возможная проблема с веб-сервером\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Тестирование HTTP запроса завершено\n";
