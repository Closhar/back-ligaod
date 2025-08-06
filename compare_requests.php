<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Сравнение запросов\n";
echo "=====================\n\n";

// Получаем данные для теста
$city = \App\Models\City::first();
$sport = \App\Models\Sport::first();
$gender = \App\Models\Gender::first();

$testData = [
    'title' => 'Compare Test ' . time(),
    'title_short' => 'CT',
    'city_id' => $city->id,
    'sport_id' => $sport->id,
    'gender_id' => $gender->id,
    'is_alien' => false
];

echo "📝 Тестовые данные:\n";
print_r($testData);
echo "\n";

// Тест 1: Прямой запрос к контроллеру
echo "1️⃣ Тест прямого запроса к контроллеру:\n";
try {
    $request = new \Illuminate\Http\Request();
    $request->merge($testData);
    
    $controller = new \App\Http\Controllers\Admin\Data\ClubController();
    $response = $controller->store($request);
    
    echo "   Status: " . $response->getStatusCode() . "\n";
    echo "   Content: " . substr($response->getContent(), 0, 200) . "...\n";
    
    if ($response->getStatusCode() === 201) {
        echo "   ✅ Прямой запрос работает\n";
        
        // Удаляем тестовый клуб
        $clubData = json_decode($response->getContent(), true);
        $clubId = $clubData['id'];
        $club = \App\Models\Club::find($clubId);
        if ($club) {
            $club->delete();
            echo "   ✅ Тестовый клуб удален\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 2: HTTP запрос через curl
echo "2️⃣ Тест HTTP запроса через curl:\n";
try {
    $jsonData = json_encode($testData);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://p.sportrep.ru/api/clubs');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    echo "   HTTP Code: {$httpCode}\n";
    echo "   Response: " . substr($response, 0, 200) . "...\n";
    
    if ($error) {
        echo "   ❌ Curl Error: {$error}\n";
    }
    
    if ($httpCode === 201) {
        echo "   ✅ HTTP запрос работает\n";
        
        // Удаляем тестовый клуб
        $responseData = json_decode($response, true);
        if (isset($responseData['id'])) {
            $club = \App\Models\Club::find($responseData['id']);
            if ($club) {
                $club->delete();
                echo "   ✅ Тестовый клуб удален\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 3: Запрос как с фронтенда
echo "3️⃣ Тест запроса как с фронтенда:\n";
try {
    $jsonData = json_encode($testData);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://p.sportrep.ru/api/clubs');
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
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    curl_close($ch);
    
    echo "   HTTP Code: {$httpCode}\n";
    echo "   Response: " . substr($response, 0, 200) . "...\n";
    
    if ($error) {
        echo "   ❌ Curl Error: {$error}\n";
    }
    
    if ($httpCode === 201) {
        echo "   ✅ Запрос как с фронтенда работает\n";
        
        // Удаляем тестовый клуб
        $responseData = json_decode($response, true);
        if (isset($responseData['id'])) {
            $club = \App\Models\Club::find($responseData['id']);
            if ($club) {
                $club->delete();
                echo "   ✅ Тестовый клуб удален\n";
            }
        }
    } else {
        echo "   ❌ Запрос как с фронтенда не работает\n";
        echo "   📋 Verbose Log:\n";
        echo $verboseLog . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 4: Проверка валидации
echo "4️⃣ Тест валидации данных:\n";
try {
    $validator = \Validator::make($testData, [
        'title' => 'required|string|max:255',
        'title_short' => 'nullable|string|max:100',
        'city_id' => 'nullable|exists:cities,id',
        'sport_id' => 'required|exists:sports,id',
        'gender_id' => 'required|exists:genders,id',
        'is_alien' => 'boolean'
    ]);
    
    if ($validator->passes()) {
        echo "   ✅ Валидация проходит\n";
    } else {
        echo "   ❌ Ошибки валидации:\n";
        foreach ($validator->errors()->all() as $error) {
            echo "      - {$error}\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка валидации: " . $e->getMessage() . "\n";
}
echo "\n";

echo "🏁 Сравнение завершено\n"; 