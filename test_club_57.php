<?php

require_once 'vendor/autoload.php';

use App\Models\Club;
use App\Models\PersonClubMembership;

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Тест клуба с ID 57 ===\n\n";

// 1. Проверяем, существует ли клуб с ID 57
$club = Club::find(57);
if ($club) {
    echo "✅ Клуб с ID 57 найден:\n";
    echo "   - ID: {$club->id}\n";
    echo "   - Title: {$club->title}\n";
    echo "   - Name (атрибут): {$club->name}\n";
    echo "   - Full Info: {$club->full_info}\n";
    echo "   - Slug: {$club->slug}\n";
} else {
    echo "❌ Клуб с ID 57 НЕ найден в таблице clubs\n";
}

echo "\n";

// 2. Проверяем членства в клубе с ID 57
$memberships = PersonClubMembership::where('club_id', 57)->get();
echo "📊 Членства в клубе с ID 57:\n";
echo "   - Всего записей: {$memberships->count()}\n";

foreach ($memberships as $membership) {
    echo "   - Membership ID: {$membership->id}, Person ID: {$membership->person_id}\n";
}

echo "\n";

// 3. Проверяем активные членства с загрузкой клуба
$activeMemberships = PersonClubMembership::with('club')->where('club_id', 57)->whereNull('left_at')->get();
echo "🔍 Активные членства в клубе с ID 57 (с загрузкой клуба):\n";
echo "   - Всего активных записей: {$activeMemberships->count()}\n";

foreach ($activeMemberships as $membership) {
    echo "   - Membership ID: {$membership->id}\n";
    echo "     Club ID: {$membership->club_id}\n";
    echo "     Club loaded: " . ($membership->club ? 'YES' : 'NO') . "\n";
    if ($membership->club) {
        echo "     Club title: {$membership->club->title}\n";
        echo "     Club name: {$membership->club->name}\n";
        echo "     Club full_info: {$membership->club->full_info}\n";
    }
    echo "\n";
}

echo "=== Тест завершен ===\n";
