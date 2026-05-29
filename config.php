<?php

declare(strict_types=1);

// config.php sits at the app root, so __DIR__ is the app root. The `paths`
// block below is this app's explicit filesystem map — the framework reads it
// through config('paths.*') and bakes no app-folder names into lib/. Omit a
// key and the framework's default_paths() (see lib/bootstrap.php) fills it in.
$root = __DIR__;

return [
    'app' => [
        'name' => env('APP_NAME', 'npg'),
        'env' => env('APP_ENV', 'production'),
        'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
        'url' => env('APP_URL', 'http://npgx.test'),
    ],
    'db' => [
        'dsn' => env('DB_DSN', ''),
        'user' => env('DB_USER', ''),
        'password' => env('DB_PASSWORD', ''),
    ],
    'session' => [
        'name' => env('SESSION_NAME', 'npg_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', '0'),
    ],
    'paths' => [
        'app' => $root . '/app',
        'views' => $root . '/app/views',
        'handlers' => $root . '/app/handlers',
        'routes' => $root . '/routes.php',
        'middleware' => $root . '/middleware.php',
        'migrations' => $root . '/migrations',
        'tests' => $root . '/tests',
        'storage' => $root . '/storage',
        'logs' => $root . '/storage/logs',
    ],
];
