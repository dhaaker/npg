<?php

declare(strict_types=1);

/**
 * Forward-only migration runner over migrations/NNN_*.sql. Files are applied in
 * filename order and recorded in a `migrations` table so re-running is a no-op.
 * To change schema you write a new migration — never edit an applied one.
 */

/**
 * Create the bookkeeping table if it does not exist yet. IF NOT EXISTS and
 * CURRENT_TIMESTAMP are portable across pdo_pgsql and pdo_sqlite.
 */
function ensure_migrations_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS migrations ('
        . 'name TEXT PRIMARY KEY, '
        . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        . ')',
    );
}

/**
 * Migration filenames on disk (basenames), sorted ascending. The numeric
 * NNN_ prefix makes a plain string sort the correct apply order.
 *
 * @return array<int, string>
 */
function migration_files(string $dir): array
{
    $paths = glob(rtrim($dir, '/') . '/[0-9]*.sql') ?: [];
    $names = array_map('basename', $paths);
    sort($names);

    return $names;
}

/**
 * Names already recorded as applied.
 *
 * @return array<int, string>
 */
function applied_migrations(): array
{
    return array_column(query_all('SELECT name FROM migrations'), 'name');
}

/**
 * Migration files on disk that have not been applied yet, in apply order.
 *
 * @return array<int, string>
 */
function pending_migrations(string $dir): array
{
    $applied = applied_migrations();

    return array_values(array_diff(migration_files($dir), $applied));
}

/**
 * Apply a single migration atomically: run its (possibly multi-statement) SQL
 * via exec(), then record it. exec() is used instead of query() because a
 * migration file may contain several statements and query() prepares only one.
 */
function apply_migration(string $dir, string $name): void
{
    $path = rtrim($dir, '/') . '/' . $name;

    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Migration file not readable: {$path}");
    }

    tx(function () use ($sql, $name) {
        db()->exec($sql);
        query('INSERT INTO migrations (name) VALUES (?)', [$name]);
    });
}

/**
 * Ensure the table exists, then apply every pending migration in order.
 * Returns the names just applied (empty array means there was nothing to do).
 *
 * @return array<int, string>
 */
function run_migrations(string $dir): array
{
    ensure_migrations_table();

    $applied = [];
    foreach (pending_migrations($dir) as $name) {
        apply_migration($dir, $name);
        $applied[] = $name;
    }

    return $applied;
}
