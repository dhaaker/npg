<?php

declare(strict_types=1);

// Test runner: loads the framework + harness, then includes every *_test.php
// file in this directory. Exits non-zero if any test failed so CI / `./npg test`
// can gate on it. Run directly with: php tests/run.php [tests/some_test.php]

define('BASE_PATH', dirname(__DIR__));
define('LIB_PATH', BASE_PATH . '/lib');

require BASE_PATH . '/lib/bootstrap.php';
require __DIR__ . '/harness.php';
require __DIR__ . '/support.php';

// Tests run against a dedicated Postgres test database, configured the
// Laravel way: bootstrap loaded .env above; here we override onto .env.testing
// so config('db.dsn') points at the _test database for the whole suite.
$envFile = BASE_PATH . '/.env.testing';
if (!is_file($envFile)) {
    fwrite(STDERR, "Missing .env.testing — copy .env.testing.example and point it at your test database.\n");
    exit(2);
}
load_env($envFile);
load_config(BASE_PATH . '/config.php');

// Destructive-op safety net: the suite truncates tables, so never let it run
// against anything but a clearly-named test database.
if (!str_contains((string) config('db.dsn'), 'test')) {
    fwrite(STDERR, "Refusing to run: db.dsn does not look like a test database.\n");
    exit(2);
}

// Migrate the test database once up front so tests share the real schema.
try {
    run_migrations(BASE_PATH . '/migrations');
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot prepare test DB ({$e->getMessage()}). Create it first: createdb npg_test\n");
    exit(2);
}

$files = array_slice($argv, 1);
if ($files === []) {
    $files = glob(__DIR__ . '/*_test.php') ?: [];
    sort($files);
}

foreach ($files as $file) {
    $path = realpath($file);
    if ($path === false || !is_file($path)) {
        fwrite(STDERR, "Test file not found: {$file}\n");
        exit(2);
    }

    fwrite(STDOUT, basename($path) . "\n");
    require $path;
}

$summary = $GLOBALS['__npg_tests'];
fwrite(STDOUT, "\n");

if ($summary['failed'] > 0) {
    fwrite(STDOUT, "\033[31mFAILED\033[0m: {$summary['passed']} passed, {$summary['failed']} failed\n");
    exit(1);
}

fwrite(STDOUT, "\033[32mOK\033[0m: {$summary['passed']} passed\n");
exit(0);
