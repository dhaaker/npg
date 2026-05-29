<?php

declare(strict_types=1);

// Requiring this file loads the framework's helper functions (in dependency
// order). It does NOT touch the filesystem layout — that happens in boot(),
// which the app's entry point calls with its own root. This keeps lib/ free of
// any assumption about where a specific app's files live, so the same lib/ can
// be vendored into any app.
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/request.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/migrate.php';
require_once __DIR__ . '/scaffold.php';
require_once __DIR__ . '/errors.php';

/**
 * Boot the framework for an app rooted at $appRoot: load its environment and
 * config, then register its filesystem paths. The app entry point
 * (public/index.php, the npg CLI, the test runner) is the single place that
 * knows where the app lives and calls this once. Pass $envPath to load a
 * non-default env file (the test runner points it at .env.testing).
 */
function boot(string $appRoot, ?string $envPath = null): void
{
    load_env($envPath ?? $appRoot . '/.env');
    load_config($appRoot . '/config.php');
    register_paths($appRoot);
}

/**
 * The framework's default filesystem layout for an app at $appRoot. An app's
 * config.php may override any of these under its `paths` key; register_paths()
 * merges the overrides on top of these defaults.
 *
 * @return array<string, string>
 */
function default_paths(string $appRoot): array
{
    return [
        'root' => $appRoot,
        'app' => $appRoot . '/app',
        'views' => $appRoot . '/app/views',
        'handlers' => $appRoot . '/app/handlers',
        'routes' => $appRoot . '/routes.php',
        'middleware' => $appRoot . '/middleware.php',
        'migrations' => $appRoot . '/migrations',
        'storage' => $appRoot . '/storage',
        'logs' => $appRoot . '/storage/logs',
    ];
}

/**
 * Merge the app's own `paths` overrides on top of the framework defaults and
 * store the result back in config, so the rest of the framework reads layout
 * through config('paths.*') with no app-folder names baked into lib/.
 */
function register_paths(string $appRoot): void
{
    $config = $GLOBALS['__npg_config'] ?? [];
    $overrides = is_array($config['paths'] ?? null) ? $config['paths'] : [];
    $config['paths'] = [...default_paths($appRoot), ...$overrides];
    $GLOBALS['__npg_config'] = $config;
}
