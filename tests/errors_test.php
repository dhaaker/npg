<?php

declare(strict_types=1);

test('source_excerpt() returns the window around the fault line', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'npg_src_');
    file_put_contents($tmp, "one\ntwo\nthree\nfour\nfive\n");

    $excerpt = source_excerpt($tmp, 3, 1);
    unlink($tmp);

    assert_same([2 => 'two', 3 => 'three', 4 => 'four'], $excerpt);
});

test('source_excerpt() returns empty for an unreadable file', function () {
    assert_same([], source_excerpt('/does/not/exist.php', 10));
});

test('render_debug_page() shows the message, file:line and last SQL', function () {
    fresh_database();
    query_all('SELECT 1 AS one');

    $e = new RuntimeException('boom while exploding');
    $page = render_debug_page($e);

    assert_true(str_contains($page, 'boom while exploding'), 'should contain the exception message');
    assert_true(str_contains($page, e($e->getFile())), 'should contain the source file');
    assert_true(str_contains($page, (string) $e->getLine()), 'should contain the line number');
    assert_true(str_contains($page, 'SELECT 1 AS one'), 'should contain the last SQL that ran');
});

test('generic_error_page() leaks no internals', function () {
    $page = generic_error_page();

    assert_true(str_contains($page, '500'), 'should mention the status');
    assert_true(!str_contains($page, 'SELECT'), 'must not leak SQL');
    assert_true(!str_contains($page, '/lib/'), 'must not leak file paths');
});

test('log_line() appends one parseable JSON object per call', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'npg_log_');

    log_line('error', 'first', ['code' => 1], $tmp);
    log_line('warning', 'second', [], $tmp);

    $lines = array_values(array_filter(explode("\n", (string) file_get_contents($tmp))));
    unlink($tmp);

    assert_same(2, count($lines), 'each call writes exactly one line');

    $first = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
    assert_same('error', $first['level']);
    assert_same('first', $first['message']);
    assert_same(1, $first['code']);
    assert_true(array_key_exists('time', $first), 'entry should carry a timestamp');

    $second = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
    assert_same('warning', $second['level']);
    assert_same('second', $second['message']);
});
