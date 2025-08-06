<?php

require_once 'vendor/autoload.php';

// Инициализация Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Тестирование middleware\n";
echo "=========================\n\n";

// 1. Проверяем зарегистрированные middleware
echo "1. Зарегистрированные middleware:\n";
try {
    $middleware = app('router')->getMiddleware();
    foreach ($middleware as $name => $class) {
        echo "   {$name}: {$class}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка получения middleware: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. Проверяем middleware для API маршрутов
echo "2. Middleware для API маршрутов:\n";
try {
    $routes = \Route::getRoutes();
    $apiRoutes = [];
    
    foreach ($routes as $route) {
        if (strpos($route->uri(), 'api/') === 0) {
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
    echo "   ❌ Ошибка проверки API маршрутов: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Проверяем CORS middleware
echo "3. Проверка CORS middleware:\n";
try {
    $corsMiddleware = new \App\Http\Middleware\CorsMiddleware();
    echo "   ✅ CORS middleware создан успешно\n";
    
    // Создаем тестовый запрос
    $request = new \Illuminate\Http\Request();
    $request->headers->set('Origin', 'https://example.com');
    $request->headers->set('Access-Control-Request-Method', 'POST');
    
    // Создаем тестовый ответ
    $response = new \Illuminate\Http\Response('test');
    
    // Тестируем middleware
    $next = function($request) use ($response) {
        return $response;
    };
    
    $result = $corsMiddleware->handle($request, $next);
    
    echo "   ✅ CORS middleware работает корректно\n";
    echo "   Headers в ответе:\n";
    foreach ($result->headers->all() as $name => $values) {
        if (strpos($name, 'Access-Control') === 0) {
            echo "   {$name}: " . implode(', ', $values) . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка CORS middleware: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Проверяем аутентификацию
echo "4. Проверка аутентификации:\n";
try {
    $auth = app('auth');
    echo "   ✅ Auth фасад доступен\n";
    
    if ($auth->check()) {
        echo "   👤 Пользователь аутентифицирован: " . $auth->user()->name . "\n";
    } else {
        echo "   👤 Пользователь не аутентифицирован\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки аутентификации: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Проверяем сессии
echo "5. Проверка сессий:\n";
try {
    $session = app('session');
    echo "   ✅ Session фасад доступен\n";
    echo "   Session ID: " . $session->getId() . "\n";
    echo "   Session driver: " . config('session.driver') . "\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка проверки сессий: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Проверяем CSRF
echo "6. Проверка CSRF:\n";
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

// 7. Тестируем простой API запрос без middleware
echo "7. Тест API без middleware:\n";
try {
    $city = \App\Models\City::first();
    $sport = \App\Models\Sport::first();
    $gender = \App\Models\Gender::first();
    
    $testData = [
        'title' => 'Middleware Тест ' . time(),
        'title_short' => 'МТ',
        'city_id' => $city->id,
        'sport_id' => $sport->id,
        'gender_id' => $gender->id,
        'is_alien' => false
    ];
    
    $request = new \Illuminate\Http\Request();
    $request->merge($testData);
    
    // Создаем контроллер напрямую
    $controller = new \App\Http\Controllers\Admin\Data\ClubController();
    $response = $controller->store($request);
    
    echo "   📊 Результат:\n";
    echo "   Status: " . $response->getStatusCode() . "\n";
    
    if ($response->getStatusCode() === 201) {
        echo "   ✅ API работает без middleware\n";
        
        // Удаляем тестовый клуб
        $clubData = json_decode($response->getContent(), true);
        $clubId = $clubData['id'];
        $club = \App\Models\Club::find($clubId);
        if ($club) {
            $club->delete();
            echo "   ✅ Тестовый клуб удален\n";
        }
    } else {
        echo "   ❌ API не работает без middleware\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка теста без middleware: " . $e->getMessage() . "\n";
}
echo "\n";

echo "🏁 Тестирование middleware завершено\n"; 