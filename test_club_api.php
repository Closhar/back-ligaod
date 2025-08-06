<?php

require_once 'vendor/autoload.php';

use App\Models\Club;
use App\Models\City;
use App\Models\Sport;
use App\Models\Gender;

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Тестируем создание клуба...\n";

    // Проверяем, есть ли необходимые данные
    $city = City::first();
    $sport = Sport::first();
    $gender = Gender::first();

    if (!$city) {
        echo "❌ Нет городов в базе данных\n";
        exit;
    }

    if (!$sport) {
        echo "❌ Нет спортов в базе данных\n";
        exit;
    }

    if (!$gender) {
        echo "❌ Нет полов в базе данных\n";
        exit;
    }

    echo "✅ Найдены необходимые данные:\n";
    echo "   Город: {$city->title}\n";
    echo "   Спорт: {$sport->title}\n";
    echo "   Пол: {$gender->title}\n";

    // Тестируем создание клуба
    $clubData = [
        'title' => 'Тестовый клуб ' . time(),
        'title_short' => 'ТК',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];

    echo "📝 Создаем клуб с данными:\n";
    print_r($clubData);

    $club = Club::create($clubData);

    echo "✅ Клуб успешно создан с ID: {$club->id}\n";

    // Удаляем тестовый клуб
    $club->delete();
    echo "🗑️ Тестовый клуб удален\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}
