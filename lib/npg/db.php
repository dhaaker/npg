<?php

declare(strict_types=1);

/**
 * The single bootstrap-owned PDO connection, created lazily on first use from
 * config('db.dsn'). This is the one deliberate stateful global in the data
 * layer — there is no per-call connection injection.
 */
function db(): PDO
{
    if (isset($GLOBALS['__npg_pdo'])) {
        return $GLOBALS['__npg_pdo'];
    }

    $pdo = new PDO(
        (string) config('db.dsn'),
        (string) config('db.user', ''),
        (string) config('db.password', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );

    $GLOBALS['__npg_pdo'] = $pdo;

    return $pdo;
}

/**
 * Shared execution path for every query helper: capture the SQL + params for
 * the debug page, prepare, bind, and execute. Keeping this in one place means
 * "last SQL" capture and parameter binding can never be skipped at a call site.
 */
function db_execute(string $sql, array $params = []): PDOStatement
{
    $GLOBALS['__npg_last_sql'] = ['sql' => $sql, 'params' => $params];

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement;
}

/**
 * Run a write (INSERT/UPDATE/DELETE/DDL) and return the affected row count.
 */
function query(string $sql, array $params = []): int
{
    return db_execute($sql, $params)->rowCount();
}

/**
 * Fetch a single row as an associative array, or null when there is no match.
 */
function query_one(string $sql, array $params = []): ?array
{
    $row = db_execute($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch every matching row as an array of associative arrays.
 *
 * @return array<int, array<string, mixed>>
 */
function query_all(string $sql, array $params = []): array
{
    return db_execute($sql, $params)->fetchAll();
}

/**
 * Id of the last inserted row. Postgres needs the sequence name to be explicit.
 */
function last_insert_id(?string $name = null): string
{
    return db()->lastInsertId($name);
}

/**
 * Run $fn inside a transaction: commit on success, roll back and rethrow on any
 * throwable. Returns whatever $fn returns.
 */
function tx(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $result = $fn();
        $pdo->commit();

        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();

        throw $e;
    }
}

/**
 * The most recently executed SQL + params, for the debug page. Null until the
 * first query runs.
 *
 * @return array{sql: string, params: array}|null
 */
function last_sql(): ?array
{
    return $GLOBALS['__npg_last_sql'] ?? null;
}

/**
 * Drop the cached connection and last-SQL capture. The test seam that lets the
 * harness point the DSN at the Postgres test database before the first query.
 */
function db_reset(): void
{
    unset($GLOBALS['__npg_pdo'], $GLOBALS['__npg_last_sql']);
}
