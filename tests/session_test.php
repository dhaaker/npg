<?php

declare(strict_types=1);

// Session, CSRF, and flash plumbing in lib/session.php. These helpers only read
// and write $_SESSION plus a few request-scoped globals, so reset_session()
// (not a real cookie-backed session) is enough to drive them in the CLI. No DB.
// The request factory is named session_request() to avoid colliding with
// make_request() in auth_test.php — every *_test.php shares one process.

function session_request(string $method, array $post = [], array $headers = []): Request
{
    return new Request(
        method: $method,
        path: '/',
        query: [],
        post: $post,
        headers: $headers,
        body: '',
    );
}

test('csrf_field() emits a hidden input carrying the session token', function () {
    reset_session();

    $token = csrf_token();
    assert_same(
        '<input type="hidden" name="_csrf" value="' . $token . '">',
        csrf_field(),
    );
});

test('csrf_middleware passes safe methods through without a token', function () {
    reset_session();

    foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
        $next = fn (Request $request) => 'reached';
        assert_same('reached', csrf_middleware(session_request($method), $next), "{$method} should pass through");
    }
});

test('csrf_middleware aborts unsafe methods with a missing or forged token', function () {
    reset_session();
    csrf_token();

    $next = fn (Request $request) => fail('handler must not run when CSRF fails');

    $missing = csrf_middleware(session_request('POST'), $next);
    assert_true($missing instanceof Html, 'a missing token should abort');
    assert_same(419, $missing->status);

    $forged = csrf_middleware(session_request('POST', ['_csrf' => 'forged']), $next);
    assert_true($forged instanceof Html, 'a forged token should abort');
    assert_same(419, $forged->status);
});

test('csrf_middleware calls the handler for unsafe methods with a valid token', function () {
    reset_session();
    $token = csrf_token();

    $next = fn (Request $request) => 'reached';

    assert_same(
        'reached',
        csrf_middleware(session_request('POST', ['_csrf' => $token]), $next),
        'matching POST field should pass',
    );
    assert_same(
        'reached',
        csrf_middleware(session_request('POST', [], ['X-Csrf-Token' => $token]), $next),
        'matching header should pass',
    );
    assert_same(
        'reached',
        csrf_middleware(session_request('POST', [], ['x-csrf-token' => $token]), $next),
        'header match is case-insensitive (HTTP header names are case-insensitive)',
    );
});

test('session_middleware invokes the handler and returns its result', function () {
    reset_session();

    $result = session_middleware(session_request('GET'), fn (Request $request) => 'handled');
    assert_same('handled', $result);
});

test('flash() accumulates per key and is only visible after flash_rotate()', function () {
    reset_session();

    assert_same([], flashes(), 'no flashes before anything is written');

    flash('error', 'first');
    flash('error', 'second');
    assert_same([], flashes(), 'flashes are hidden during the writing request');

    flash_rotate();
    assert_same(['error' => ['first', 'second']], flashes(), 'both messages survive one rotate');
});

test('flash_errors()/flash_old() become readable via errors()/old() after a rotate', function () {
    reset_session();

    assert_same([], errors(), 'no errors before anything is written');
    assert_same('default', old('email', 'default'), 'old() returns the default when unset');

    flash_errors(['email' => ['Required']]);
    flash_old(['email' => 'ada@example.com']);

    flash_rotate();
    assert_same(['email' => ['Required']], errors());
    assert_same('ada@example.com', old('email'));
    assert_same('fallback', old('missing', 'fallback'), 'unknown key still returns the default');
});

test('reset_session() clears flashes, errors, and old input', function () {
    reset_session();

    flash('error', 'boom');
    flash_errors(['email' => ['Required']]);
    flash_old(['email' => 'ada@example.com']);
    flash_rotate();

    reset_session();
    assert_same([], flashes());
    assert_same([], errors());
    assert_same('', old('email'));
});
