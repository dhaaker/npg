<?php

declare(strict_types=1);

test('parse_env() parses simple KEY=VALUE', function () {
    assert_same(['FOO' => 'bar'], parse_env("FOO=bar\n"));
});

test('parse_env() strips double quotes from values', function () {
    assert_same(['KEY' => 'hello world'], parse_env("KEY=\"hello world\"\n"));
});

test('parse_env() strips single quotes from values', function () {
    assert_same(['KEY' => 'hello'], parse_env("KEY='hello'\n"));
});

test('parse_env() preserves equals signs inside values', function () {
    assert_same(['URL' => 'a=b=c'], parse_env("URL=a=b=c\n"));
});

test('parse_env() skips blank lines and comments', function () {
    $input = <<<'ENV'
# comment
KEY=value

# another comment
ENV;
    assert_same(['KEY' => 'value'], parse_env($input));
});

test('parse_env() handles export prefix', function () {
    assert_same(['FOO' => 'bar'], parse_env("export FOO=bar\n"));
});

test('env() returns value from loaded store', function () {
    $GLOBALS['__npg_env'] = ['TEST_KEY' => 'from_store'];

    assert_same('from_store', env('TEST_KEY'));
});

test('env() returns default when key is missing', function () {
    $GLOBALS['__npg_env'] = [];

    assert_same('fallback', env('MISSING_KEY', 'fallback'));
    assert_same(null, env('ALSO_MISSING'));
});

test('load_env() with missing file sets empty store', function () {
    load_env(BASE_PATH . '/.env.does-not-exist');

    assert_same([], $GLOBALS['__npg_env']);
});

test('load_env() reads a real .env file', function () {
    $path = sys_get_temp_dir() . '/npg_env_test_' . uniqid('', true) . '.env';
    file_put_contents($path, "LOADED_KEY=loaded_value\n");

    try {
        load_env($path);
        assert_same('loaded_value', env('LOADED_KEY'));
    } finally {
        @unlink($path);
    }
});
