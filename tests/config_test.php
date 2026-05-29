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
    // Snapshot the live env/config so we restore the suite's test-database
    // config exactly — reloading from a fixed .env file here would switch the
    // whole suite onto the wrong database mid-run.
    $envSnapshot = $GLOBALS['__npg_env'] ?? null;
    $configSnapshot = $GLOBALS['__npg_config'] ?? null;

    $path = sys_get_temp_dir() . '/npg_config_test_' . uniqid('', true) . '.env';
    file_put_contents($path, "APP_NAME=test-app\nDB_DSN=pgsql:host=test\n");

    try {
        load_env($path);
        load_config(BASE_PATH . '/config.php');

        assert_same('test-app', config('app.name'));
        assert_same('pgsql:host=test', config('db.dsn'));
    } finally {
        @unlink($path);
        $GLOBALS['__npg_env'] = $envSnapshot;
        $GLOBALS['__npg_config'] = $configSnapshot;
    }
});

test('config() returns default for unknown key', function () {
    assert_same('nope', config('does.not.exist', 'nope'));
});
