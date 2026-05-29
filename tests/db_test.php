<?php

declare(strict_types=1);

// These run against the real, migrated `users` table in the test database.
// fresh_database() (see support.php) truncates everything first so each test
// starts from empty tables with reset identity sequences.

test('query() runs writes and returns affected row count', function () {
    fresh_database();

    $affected = query(
        'INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)',
        ['ada@example.com', 'Ada', 'x'],
    );

    assert_same(1, $affected);
});

test('last_insert_id() returns the id of the inserted row', function () {
    fresh_database();

    query(
        'INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)',
        ['ada@example.com', 'Ada', 'x'],
    );

    assert_same('1', last_insert_id('users_id_seq'));
});

test('query_one() returns one assoc row, or null when no match', function () {
    fresh_database();

    query(
        'INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)',
        ['ada@example.com', 'Ada', 'x'],
    );

    $row = query_one('SELECT * FROM users WHERE name = ?', ['Ada']);
    assert_same('Ada', $row['name']);
    assert_same(1, (int) $row['id']);

    assert_same(null, query_one('SELECT * FROM users WHERE name = ?', ['Grace']));
});

test('query_all() returns every matching row as an array', function () {
    fresh_database();

    query('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)', ['ada@example.com', 'Ada', 'x']);
    query('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)', ['grace@example.com', 'Grace', 'x']);

    $rows = query_all('SELECT name FROM users ORDER BY id');

    assert_same(2, count($rows));
    assert_same('Ada', $rows[0]['name']);
    assert_same('Grace', $rows[1]['name']);
});

test('last_sql() reflects the most recent SQL + params', function () {
    fresh_database();

    query_one('SELECT * FROM users WHERE name = ?', ['Ada']);

    $last = last_sql();
    assert_same('SELECT * FROM users WHERE name = ?', $last['sql']);
    assert_same(['Ada'], $last['params']);
});

test('tx() commits on success', function () {
    fresh_database();

    $result = tx(function () {
        query('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)', ['ada@example.com', 'Ada', 'x']);

        return 'done';
    });

    assert_same('done', $result);
    assert_same(1, (int) query_one('SELECT COUNT(*) AS c FROM users')['c']);
});

test('tx() rolls back and rethrows on failure', function () {
    fresh_database();

    query('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)', ['ada@example.com', 'Ada', 'x']);

    assert_throws(RuntimeException::class, function () {
        tx(function () {
            query('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)', ['grace@example.com', 'Grace', 'x']);

            throw new RuntimeException('boom');
        });
    });

    assert_same(1, (int) query_one('SELECT COUNT(*) AS c FROM users')['c']);
});
