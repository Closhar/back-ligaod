<?php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Тест API endpoints ===\n\n";

// 1. Тест /people/clubs
echo "🔍 Тестируем /people/clubs:\n";
try {
    $request = new \Illuminate\Http\Request();
    $controller = new \App\Http\Controllers\Api\PersonController();
    $response = $controller->clubs();
    $data = json_decode($response->getContent(), true);

    if ($data['success']) {
        echo "✅ Успешно загружено клубов: " . count($data['data']) . "\n";
        if (count($data['data']) > 0) {
            $firstClub = $data['data'][0];
            echo "   Первый клуб:\n";
            echo "     - ID: {$firstClub['id']}\n";
            echo "     - Title: {$firstClub['title']}\n";
            echo "     - Name: " . (isset($firstClub['name']) ? $firstClub['name'] : 'ОТСУТСТВУЕТ') . "\n";
        }
    } else {
        echo "❌ Ошибка: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Тест /people/sports
echo "🔍 Тестируем /people/sports:\n";
try {
    $request = new \Illuminate\Http\Request();
    $controller = new \App\Http\Controllers\Api\PersonController();
    $response = $controller->sports();
    $data = json_decode($response->getContent(), true);

    if ($data['success']) {
        echo "✅ Успешно загружено видов спорта: " . count($data['data']) . "\n";
        if (count($data['data']) > 0) {
            $firstSport = $data['data'][0];
            echo "   Первый вид спорта:\n";
            echo "     - ID: {$firstSport['id']}\n";
            echo "     - Title: {$firstSport['title']}\n";
            echo "     - Name: " . (isset($firstSport['name']) ? $firstSport['name'] : 'ОТСУТСТВУЕТ') . "\n";
        }
    } else {
        echo "❌ Ошибка: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Тест /people/positions
echo "🔍 Тестируем /people/positions:\n";
try {
    $request = new \Illuminate\Http\Request();
    $controller = new \App\Http\Controllers\Api\PersonController();
    $response = $controller->positions();
    $data = json_decode($response->getContent(), true);

    if ($data['success']) {
        echo "✅ Успешно загружено должностей: " . count($data['data']) . "\n";
        if (count($data['data']) > 0) {
            $firstPosition = $data['data'][0];
            echo "   Первая должность:\n";
            echo "     - ID: {$firstPosition['id']}\n";
            echo "     - Name: {$firstPosition['name']}\n";
        }
    } else {
        echo "❌ Ошибка: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Тест /people/ampluas
echo "🔍 Тестируем /people/ampluas:\n";
try {
    $request = new \Illuminate\Http\Request();
    $controller = new \App\Http\Controllers\Api\PersonController();
    $response = $controller->ampluas();
    $data = json_decode($response->getContent(), true);

    if ($data['success']) {
        echo "✅ Успешно загружено амплуа: " . count($data['data']) . "\n";
        if (count($data['data']) > 0) {
            $firstAmplua = $data['data'][0];
            echo "   Первое амплуа:\n";
            echo "     - ID: {$firstAmplua['id']}\n";
            echo "     - Name: {$firstAmplua['name']}\n";
        }
    } else {
        echo "❌ Ошибка: " . $data['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n=== Тест завершен ===\n";
