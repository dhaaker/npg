<?php

declare(strict_types=1);

// Build a throwaway migrations dir of *unapplied* filenames. We only ever read
// from it (migration_files / pending_migrations) — never apply it — so these
// tests can't create stray tables in the persistent test database.
function make_migrations_dir(): string
{
    $dir = sys_get_temp_dir() . '/npg_migrate_test_' . uniqid('', true);
    mkdir($dir);

    file_put_contents($dir . '/100_create_widgets.sql', 'SELECT 1;');
    file_put_contents($dir . '/101_seed_widgets.sql', 'SELECT 1;');

    return $dir;
}

function remove_migrations_dir(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dir);
}

test('migration_files() returns NNN_*.sql basenames sorted by prefix', function () {
    $dir = make_migrations_dir();

    try {
        assert_same(
            ['100_create_widgets.sql', '101_seed_widgets.sql'],
            migration_files($dir),
        );
    } finally {
        remove_migrations_dir($dir);
    }
});

test('pending_migrations() returns files on disk that are not yet applied', function () {
    $dir = make_migrations_dir();

    try {
        // None of these temp files are recorded in the migrations table.
        assert_same(
            ['100_create_widgets.sql', '101_seed_widgets.sql'],
            pending_migrations($dir),
        );
    } finally {
        remove_migrations_dir($dir);
    }
});

test('run_migrations() is a no-op once the real migrations are applied', function () {
    // run.php migrated the test DB at startup, so the real dir has nothing left.
    $real = BASE_PATH . '/migrations';

    assert_same([], run_migrations($real));
    assert_same([], pending_migrations($real));
});

test('startup migrations are recorded and created the users table', function () {
    assert_true(
        in_array('001_create_users.sql', applied_migrations(), true),
        '001_create_users.sql should be recorded as applied',
    );

    $table = query_one("SELECT to_regclass('public.users') AS t")['t'];
    assert_same('users', $table);
});
