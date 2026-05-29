<?php

declare(strict_types=1);

test('config_get() returns nested value via dot path', function () {
    $config = [
        'app' => ['name' => 'npg'],
        'db' => ['dsn' => 'pgsql:host=127.0.0.1'],
    ];

    assert_same('npg', config_get($config, 'app.name'));
    assert_same('pgsql:host=127.0.0.1', config_get($config, 'db.dsn'));
});

test('config_get() returns default for missing key', function () {
    $config = ['app' => ['name' => 'npg']];

    assert_same('default', config_get($config, 'app.missing', 'default'));
    assert_same(null, config_get($config, 'missing'));
});

test('config_get() returns default for partial path', function () {
    $config = ['app' => 'not-an-array'];

    assert_same('fallback', config_get($config, 'app.name', 'fallback'));
});

test('config() reads from bootstrap-loaded config', function () {
    $path = sys_get_temp_dir() . '/npg_config_test_' . uniqid('', true) . '.env';
    file_put_contents($path, "APP_NAME=test-app\nDB_DSN=pgsql:host=test\n");

    try {
        load_env($path);
        load_config(BASE_PATH . '/config.php');

        assert_same('test-app', config('app.name'));
        assert_same('pgsql:host=test', config('db.dsn'));
    } finally {
        @unlink($path);
        load_env(BASE_PATH . '/.env');
        load_config(BASE_PATH . '/config.php');
    }
});

test('config() returns default for unknown key', function () {
    assert_same('nope', config('does.not.exist', 'nope'));
});
