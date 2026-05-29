<?php

declare(strict_types=1);

// Auth + session + CSRF. These drive the helpers directly. reset_session()
// initialises $_SESSION without starting a real (cookie-backed) PHP session, so
// the suite runs cleanly in the CLI; auth_login()/logout() skip
// session_regenerate_id() when no session is active. DB-backed tests use the
// real migrated `users` table via fresh_database() (see support.php).

function make_request(string $method, array $post = [], array $headers = []): Request
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

test('hash_password()/verify_password() round-trip', function () {
    $hash = hash_password('correct horse battery staple');

    assert_true($hash !== 'correct horse battery staple', 'password must not be stored in plaintext');
    assert_true(verify_password('correct horse battery staple', $hash), 'correct password should verify');
    assert_true(!verify_password('wrong', $hash), 'wrong password should not verify');
});

test('create_user() + auth_attempt() succeeds with the right password', function () {
    fresh_database();
    reset_session();

    create_user('ada@example.com', 'Ada', 'supersecret');

    $user = auth_attempt('ada@example.com', 'supersecret');
    assert_true($user !== null, 'valid credentials should return a user');
    assert_same('Ada', $user['name']);
    assert_true(!array_key_exists('password_hash', $user), 'auth_attempt must not leak the hash');
});

test('auth_attempt() fails on wrong password and unknown email', function () {
    fresh_database();
    reset_session();

    create_user('ada@example.com', 'Ada', 'supersecret');

    assert_same(null, auth_attempt('ada@example.com', 'nope'));
    assert_same(null, auth_attempt('grace@example.com', 'supersecret'));
});

test('csrf_token() is stable within a session', function () {
    reset_session();

    $first = csrf_token();
    assert_true($first !== '', 'token should be generated');
    assert_same($first, csrf_token(), 'token should be stable across calls');
});

test('csrf_verify() passes the matching token and rejects mismatches', function () {
    reset_session();
    $token = csrf_token();

    assert_true(csrf_verify(make_request('POST', ['_csrf' => $token])), 'matching POST field should verify');
    assert_true(
        csrf_verify(make_request('POST', [], ['X-Csrf-Token' => $token])),
        'matching header should verify',
    );
    assert_true(!csrf_verify(make_request('POST', ['_csrf' => 'forged'])), 'wrong token should fail');
    assert_true(!csrf_verify(make_request('POST')), 'missing token should fail');
});

test('login -> current_user() -> logout cycle', function () {
    fresh_database();
    reset_session();

    $created = create_user('ada@example.com', 'Ada', 'supersecret');
    assert_same(null, current_user());

    auth_login($created);
    $user = current_user();
    assert_true($user !== null, 'current_user should be set after login');
    assert_same('ada@example.com', $user['email']);

    logout();
    assert_same(null, current_user());
});

test('require_login() redirects when logged out and passes when logged in', function () {
    fresh_database();
    reset_session();

    $guard = require_login(make_request('GET'));
    assert_true($guard instanceof Redirect, 'logged-out request should be redirected');
    assert_same('/login', $guard->location);

    auth_login(create_user('ada@example.com', 'Ada', 'supersecret'));
    assert_same(null, require_login(make_request('GET')), 'logged-in request should pass');
});

test('flash() survives one flash_rotate() then clears', function () {
    reset_session();

    flash('error', 'Something went wrong');
    // Still in the "writing" request: not yet visible via flashes().
    assert_same([], flashes());

    // Next request boundary.
    flash_rotate();
    assert_same(['error' => ['Something went wrong']], flashes());

    // The request after that: flash is gone.
    flash_rotate();
    assert_same([], flashes());
});
