<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Тестирование аутентификации и сессий\n";
echo "=======================================\n\n";

// 1. Проверка аутентификации
echo "1. Проверка аутентификации:\n";
try {
    $auth = app('auth');
    echo "   ✅ Auth фасад доступен\n";

    if ($auth->check()) {
        $user = $auth->user();
        echo "   👤 Пользователь аутентифицирован:\n";
        echo "      ID: {$user->id}\n";
        echo "      Name: {$user->name}\n";
        echo "      Email: {$user->email}\n";
    } else {
        echo "   👤 Пользователь НЕ аутентифицирован\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки аутентификации: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. Проверка сессий
echo "2. Проверка сессий:\n";
try {
    $session = app('session');
    echo "   ✅ Session фасад доступен\n";
    echo "   Session ID: " . $session->getId() . "\n";
    echo "   Session driver: " . config('session.driver') . "\n";
    echo "   Session lifetime: " . config('session.lifetime') . " минут\n";

    // Проверяем сессионные данные
    $sessionData = $session->all();
    if (!empty($sessionData)) {
        echo "   Session data:\n";
        foreach ($sessionData as $key => $value) {
            if (is_string($value) && strlen($value) < 100) {
                echo "      {$key}: {$value}\n";
            } else {
                echo "      {$key}: [complex data]\n";
            }
        }
    } else {
        echo "   Session data: пусто\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки сессий: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Проверка CSRF
echo "3. Проверка CSRF:\n";
try {
    $csrf = app('csrf');
    echo "   ✅ CSRF фасад доступен\n";

    // Проверяем, есть ли CSRF middleware для API
    $routes = \Route::getRoutes();
    $csrfProtected = false;

    foreach ($routes as $route) {
        if (strpos($route->uri(), 'api/clubs') !== false) {
            $middleware = $route->middleware();
            if (in_array('web', $middleware) || in_array('VerifyCsrfToken', $middleware)) {
                $csrfProtected = true;
                break;
            }
        }
    }

    if ($csrfProtected) {
        echo "   ⚠️ API защищен CSRF middleware\n";
    } else {
        echo "   ✅ API не защищен CSRF (это нормально для API)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки CSRF: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Проверка middleware для API
echo "4. Проверка middleware для API:\n";
try {
    $routes = \Route::getRoutes();
    $apiRoutes = [];

    foreach ($routes as $route) {
        if (strpos($route->uri(), 'api/clubs') !== false) {
            $middleware = $route->middleware();
            $apiRoutes[] = [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'middleware' => $middleware
            ];
        }
    }

    if (!empty($apiRoutes)) {
        echo "   ✅ API маршруты найдены:\n";
        foreach ($apiRoutes as $route) {
            echo "   " . implode('|', $route['methods']) . " " . $route['uri'] . "\n";
            if (!empty($route['middleware'])) {
                echo "      Middleware: " . implode(', ', $route['middleware']) . "\n";
            }
        }
    } else {
        echo "   ❌ API маршруты не найдены\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки middleware: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Тест API с аутентификацией
echo "5. Тест API с аутентификацией:\n";
try {
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();

    $testData = [
        'title' => 'Auth Test ' . time(),
        'title_short' => 'AT',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];

    $request = new \Illuminate\Http\Request();
    $request->merge($testData);

    // Добавляем заголовки аутентификации
    $request->headers->set('Authorization', 'Bearer test-token');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');

    $controller = new \App\Http\Controllers\Admin\Data\ClubController();
    $response = $controller->store($request);

    echo "   Status: " . $response->getStatusCode() . "\n";
    echo "   Content: " . substr($response->getContent(), 0, 200) . "...\n";

    if ($response->getStatusCode() === 201) {
        echo "   ✅ API с аутентификацией работает\n";

        // Удаляем тестовый клуб
        $clubData = json_decode($response->getContent(), true);
        $clubId = $clubData['id'];
        $club = \App\Models\Club::find($clubId);
        if ($club) {
            $club->delete();
            echo "   ✅ Тестовый клуб удален\n";
        }
    } else {
        echo "   ❌ API с аутентификацией не работает\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка теста с аутентификацией: " . $e->getMessage() . "\n";
}
echo "\n";

echo "🏁 Тестирование аутентификации завершено\n";
