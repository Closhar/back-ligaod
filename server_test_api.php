<?php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Быстрый тест API на сервере\n";
echo "===============================\n\n";

try {
    // Проверяем базовые данные
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();
    
    if (!$city || !$sport || !$gender) {
        echo "❌ Недостаточно данных в БД\n";
        exit(1);
    }
    
    echo "✅ Данные найдены:\n";
    echo "   Город: {$city->title}\n";
    echo "   Спорт: {$sport->title}\n";
    echo "   Пол: {$gender->title}\n\n";
    
    // Тестируем создание клуба
    $clubData = [
        'title' => 'Серверный тест ' . time(),
        'title_short' => 'СТ',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];
    
    echo "📝 Создаем тестовый клуб...\n";
    $club = \App\Models\Club::create($clubData);
    echo "✅ Клуб создан с ID: {$club->id}\n";
    
    // Удаляем тестовый клуб
    $club->delete();
    echo "✅ Тестовый клуб удален\n\n";
    
    echo "🎉 API работает корректно!\n";
    echo "Проблема может быть в:\n";
    echo "- Лимитах памяти PHP\n";
    echo "- Правах доступа к файлам\n";
    echo "- Конфигурации веб-сервера\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 