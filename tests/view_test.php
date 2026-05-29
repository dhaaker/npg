<?php

declare(strict_types=1);

test('e() escapes HTML special characters', function () {
    assert_same('&lt;script&gt;', e('<script>'));
    assert_same('a &amp; b', e('a & b'));
});

test('e() escapes both single and double quotes (ENT_QUOTES)', function () {
    assert_same('&quot;x&quot;', e('"x"'));
    assert_same('&#039;y&#039;', e("'y'"));
});

test('e() leaves plain text untouched', function () {
    assert_same('hello world', e('hello world'));
});

test('render_html() extracts context into the template and escapes it', function () {
    $body = render_html(new Html('_abort', ['status' => 418, 'message' => '<teapot>']));

    assert_true(str_contains($body, '418'), 'rendered body should contain the status');
    assert_true(str_contains($body, '&lt;teapot&gt;'), 'message should be HTML-escaped via e()');
});

test('render_html() throws when the template is missing', function () {
    assert_throws(RuntimeException::class, fn() => render_html(new Html('does/not/exist')));
});

test('partial() renders a sub-view and escapes its context via e()', function () {
    $body = partial('_abort', ['status' => 418, 'message' => '<teapot>']);

    assert_true(str_contains($body, '418'), 'rendered partial should contain the status');
    assert_true(str_contains($body, '&lt;teapot&gt;'), 'message should be HTML-escaped via e()');
});

test('partial() makes passed context available to the sub-view', function () {
    $body = partial('_404', ['message' => 'nothing here']);

    assert_true(str_contains($body, 'nothing here'), 'passed context should reach the partial');
});

test('partial() throws when the partial is missing', function () {
    assert_throws(RuntimeException::class, fn() => partial('_does_not_exist'));
});
