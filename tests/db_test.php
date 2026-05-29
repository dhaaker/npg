<?php

declare(strict_types=1);

// Swap the configured DSN to an in-memory SQLite database, reset the cached
// connection so db() rebuilds against it, run $fn, then restore Postgres config
// + connection. `sqlite::memory:` is per-connection, so every query inside $fn
// shares the one connection db() caches for the duration.
function with_sqlite(callable $fn): void
{
    $previous = $GLOBALS['__npg_config']['db'] ?? null;

    $GLOBALS['__npg_config']['db'] = [
        'dsn' => 'sqlite::memory:',
        'user' => '',
        'password' => '',
    ];
    db_reset();

    try {
        $fn();
    } finally {
        if ($previous === null) {
            unset($GLOBALS['__npg_config']['db']);
        } else {
            $GLOBALS['__npg_config']['db'] = $previous;
        }
        db_reset();
    }
}

test('query() runs DDL/writes and returns affected row count', function () {
    with_sqlite(function () {
        query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        $affected = query('INSERT INTO users (name) VALUES (?)', ['Ada']);

        assert_same(1, $affected);
    });
});

test('last_insert_id() returns the id of the inserted row', function () {
    with_sqlite(function () {
        query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        query('INSERT INTO users (name) VALUES (?)', ['Ada']);

        assert_same('1', last_insert_id());
    });
});

test('query_one() returns one assoc row, or null when no match', function () {
    with_sqlite(function () {
        query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        query('INSERT INTO users (name) VALUES (?)', ['Ada']);

        $row = query_one('SELECT * FROM users WHERE name = ?', ['Ada']);
        assert_same('Ada', $row['name']);
        assert_same(1, (int) $row['id']);

        assert_same(null, query_one('SELECT * FROM users WHERE name = ?', ['Grace']));
    });
});

test('query_all() returns every matching row as an array', function () {
    with_sqlite(function () {
        query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        query('INSERT INTO users (name) VALUES (?)', ['Ada']);
        query('INSERT INTO users (name) VALUES (?)', ['Grace']);

        $rows = query_all('SELECT name FROM users ORDER BY id');

        assert_same(2, count($rows));
        assert_same('Ada', $rows[0]['name']);
        assert_same('Grace', $rows[1]['name']);
    });
});

test('last_sql() reflects the most recent SQL + params', function () {
    with_sqlite(function () {
        query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        query_one('SELECT * FROM users WHERE name = ?', ['Ada']);

        $last = last_sql();
        assert_same('SELECT * FROM users WHERE name = ?', $last['sql']);
        assert_same(['Ada'], $last['params']);
    });
});

test('tx() commits on success', function () {
    with_sqlite(function () {
        query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        $result = tx(function () {
            query('INSERT INTO users (name) VALUES (?)', ['Ada']);

            return 'done';
        });

        assert_same('done', $result);
        assert_same(1, (int) query_one('SELECT COUNT(*) AS c FROM users')['c']);
    });
});

test('tx() rolls back and rethrows on failure', function () {
    with_sqlite(function () {
        query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        query('INSERT INTO users (name) VALUES (?)', ['Ada']);

        assert_throws(RuntimeException::class, function () {
            tx(function () {
                query('INSERT INTO users (name) VALUES (?)', ['Grace']);

                throw new RuntimeException('boom');
            });
        });

        assert_same(1, (int) query_one('SELECT COUNT(*) AS c FROM users')['c']);
    });
});
