<?php

declare(strict_types=1);

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
];
