<?php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Тестирование HTTP запроса к API\n";
echo "================================\n\n";

try {
    // Получаем необходимые данные
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();

    if (!$city || !$sport || !$gender) {
        echo "❌ Недостаточно данных для теста\n";
        exit;
    }

    // Создаем тестовые данные
    $testData = [
        'title' => 'HTTP Тестовый клуб ' . time(),
        'title_short' => 'HTTP',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false,
        'about' => 'Тестовое описание клуба через HTTP',
        'address' => 'Тестовый адрес через HTTP'
    ];

    echo "📝 Тестовые данные:\n";
    print_r($testData);
    echo "\n";

    // Создаем HTTP запрос
    $request = new \Illuminate\Http\Request();
    $request->merge($testData);

    // Устанавливаем заголовки как в реальном запросе
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('User-Agent', 'Test-Script/1.0');

    echo "🚀 Выполняем HTTP запрос к API...\n";

    // Создаем контроллер
    $controller = new \App\Http\Controllers\Admin\Data\ClubController();

    // Вызываем метод store
    $response = $controller->store($request);

    echo "📊 Результат HTTP запроса:\n";
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Headers:\n";
    foreach ($response->headers->all() as $name => $values) {
        echo "  {$name}: " . implode(', ', $values) . "\n";
    }
    echo "\nContent:\n";
    echo $response->getContent() . "\n\n";

    if ($response->getStatusCode() === 201) {
        echo "✅ HTTP запрос успешен!\n";

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
        echo "❌ HTTP запрос завершился с ошибкой\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Тестирование завершено\n";
