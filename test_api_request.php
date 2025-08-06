<?php

require_once 'vendor/autoload.php';

use App\Models\City;
use App\Models\Sport;
use App\Models\Gender;

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Тестируем API запрос для создания клуба...\n";

    // Получаем необходимые данные
    $city = City::first();
    $sport = Sport::first();
    $gender = Gender::first();

    if (!$city || !$sport || !$gender) {
        echo "❌ Недостаточно данных для теста\n";
        exit;
    }

    // Создаем тестовые данные
    $testData = [
        'title' => 'Тестовый клуб API ' . time(),
        'title_short' => 'ТКА',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false,
        'about' => 'Тестовое описание клуба',
        'address' => 'Тестовый адрес'
    ];

    echo "📝 Тестовые данные:\n";
    print_r($testData);

    // Создаем HTTP запрос
    $request = new \Illuminate\Http\Request();
    $request->merge($testData);

    // Создаем контроллер
    $controller = new \App\Http\Controllers\Admin\Data\ClubController();

    echo "🚀 Выполняем запрос к API...\n";

    // Вызываем метод store
    $response = $controller->store($request);

    echo "📊 Результат:\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";

    if ($response->getStatusCode() === 201) {
        echo "✅ API запрос успешен!\n";

        // Получаем созданный клуб
        $clubData = json_decode($response->getContent(), true);
        $clubId = $clubData['id'];

        echo "🗑️ Удаляем тестовый клуб с ID: {$clubId}\n";

        // Удаляем тестовый клуб
        $club = \App\Models\Club::find($clubId);
        if ($club) {
            $club->delete();
            echo "✅ Тестовый клуб удален\n";
        }
    } else {
        echo "❌ API запрос завершился с ошибкой\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}
