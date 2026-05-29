<?php

declare(strict_types=1);

// Shared test helpers. Loaded by run.php before any *_test.php file so every
// test can rely on these being defined exactly once.

// Reset the test database to a clean slate: truncate every table in the public
// schema except `migrations` (so the DB stays migrated), resetting identity
// sequences. DB tests call this first so each starts from empty tables.
//
// Guard: refuse to truncate anything unless the configured database is clearly
// a test database — a last line of defence against wiping the dev DB if config
// is ever pointed at the wrong place.
function fresh_database(): void
{
    if (!str_contains((string) config('db.dsn'), 'test')) {
        throw new RuntimeException('fresh_database() refused: not a test database.');
    }

    $tables = array_column(
        query_all("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'migrations'"),
        'tablename',
    );
    if ($tables === []) {
        return;
    }

    $quoted = implode(', ', array_map(fn ($t) => '"' . $t . '"', $tables));
    db()->exec("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");
}
