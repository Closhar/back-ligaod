<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
$suppressMadelineWindowsWarning = static function (callable $callback): void {
    ob_start();

    try {
        $callback();
    } finally {
        $output = ob_get_clean();

        $output = str_replace(
            "WARNING: MadelineProto runs around 10x slower on windows due to OS and PHP limitations. Make sure to deploy MadelineProto in production only on Linux or Mac OS machines for maximum performance.".PHP_EOL,
            '',
            $output
        );

        if ($output !== '') {
            echo $output;
        }
    }
};

$suppressMadelineWindowsWarning(static function (): void {
require __DIR__.'/../vendor/autoload.php';
});

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
