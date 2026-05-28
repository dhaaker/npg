<?php

declare(strict_types=1);

// Test runner: loads the framework + harness, then includes every *_test.php
// file in this directory. Exits non-zero if any test failed so CI / `./npg test`
// can gate on it. Run directly with: php tests/run.php [tests/some_test.php]

define('BASE_PATH', dirname(__DIR__));
define('LIB_PATH', BASE_PATH . '/lib');

require BASE_PATH . '/lib/bootstrap.php';
require __DIR__ . '/harness.php';

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
