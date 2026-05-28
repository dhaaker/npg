<?php

declare(strict_types=1);

// Tiny flat-assertion test harness — no PHPUnit, no Composer.
// State lives in one well-known place; assertions are plain global functions
// that throw on failure. `test()` catches the throw and records the result.

$GLOBALS['__npg_tests'] = ['passed' => 0, 'failed' => 0];

function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__npg_tests']['passed']++;
        fwrite(STDOUT, "  \033[32mPASS\033[0m  {$name}\n");
    } catch (Throwable $e) {
        $GLOBALS['__npg_tests']['failed']++;
        fwrite(STDOUT, "  \033[31mFAIL\033[0m  {$name}\n");
        fwrite(STDOUT, '        ' . $e->getMessage() . "\n");
        fwrite(STDOUT, '        ' . $e->getFile() . ':' . $e->getLine() . "\n");
    }
}

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function assert_true(bool $condition, string $message = 'expected true, got false'): void
{
    if (!$condition) {
        fail($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $prefix = $message !== '' ? $message . ': ' : '';
        fail($prefix . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_throws(string $exceptionClass, callable $fn, string $message = ''): Throwable
{
    $prefix = $message !== '' ? $message . ': ' : '';

    try {
        $fn();
    } catch (Throwable $e) {
        if (!($e instanceof $exceptionClass)) {
            fail($prefix . "expected {$exceptionClass}, got " . $e::class . ' (' . $e->getMessage() . ')');
        }

        return $e;
    }

    fail($prefix . "expected {$exceptionClass} to be thrown, but nothing was thrown");
}
