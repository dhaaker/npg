<?php

declare(strict_types=1);

// Framework test runner. Spawned as a fresh process (by `./npg test`, or run
// directly with `php lib/npg/run_tests.php [tests/some_test.php]`) so it boots
// cleanly against .env.testing. It lives in the framework (lib/npg/) and is
// vendored into every app, so apps never own or edit it — their tests/ holds
// only *_test.php files. Exits non-zero if any test failed so CI can gate on it.

$appRoot = dirname(__DIR__, 2);

define('BASE_PATH', $appRoot);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/support.php';

// Tests run against a dedicated Postgres test database, configured the
// Laravel way: boot the framework against .env.testing (instead of .env) so
// config('db.dsn') points at the _test database for the whole suite.
$envFile = $appRoot . '/.env.testing';
if (!is_file($envFile)) {
    fwrite(STDERR, "Missing .env.testing — copy .env.testing.example and point it at your test database.\n");
    exit(2);
}
boot($appRoot, $envFile);

// Destructive-op safety net: the suite truncates tables, so never let it run
// against anything but a clearly-named test database.
if (!str_contains((string) config('db.dsn'), 'test')) {
    fwrite(STDERR, "Refusing to run: db.dsn does not look like a test database.\n");
    exit(2);
}

// Migrate the test database once up front so tests share the real schema.
try {
    run_migrations(config('paths.migrations'));
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot prepare test DB ({$e->getMessage()}). Create it first: createdb <db>_test\n");
    exit(2);
}

$files = array_slice($argv, 1);
if ($files === []) {
    $files = glob(config('paths.tests') . '/*_test.php') ?: [];
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
