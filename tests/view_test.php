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
