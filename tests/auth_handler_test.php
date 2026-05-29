<?php

declare(strict_types=1);

// Exercises the example auth handlers (app/handlers/auth.php). The runner loads
// the framework but not app code, so we require the handler file under test. A
// handler returns a response *description*, so we assert on that — no HTTP, no
// rendering. Request is built inline (not via a shared factory) so this file
// stays independent of load order with the other *_test.php in the process.

require_once config('paths.handlers') . '/auth.php';

function logout_request(string $method): Request
{
    return new Request(
        method: $method,
        path: '/logout',
        query: [],
        post: [],
        headers: [],
        body: '',
    );
}

test('auth_logout() rejects non-POST so a GET link cannot sign a user out', function () {
    reset_session();

    foreach (['GET', 'HEAD'] as $method) {
        $result = auth_logout(logout_request($method));
        assert_true($result instanceof Html, "{$method} /logout must not perform the side effect");
        assert_same(405, $result->status, "{$method} /logout should be 405 Method Not Allowed");
    }
});

test('auth_logout() clears the session and redirects on POST', function () {
    fresh_database();
    reset_session();

    auth_login(create_user('ada@example.com', 'Ada', 'supersecret'));
    assert_true(current_user() !== null, 'precondition: a user is logged in');

    $result = auth_logout(logout_request('POST'));

    assert_true($result instanceof Redirect, 'POST /logout should redirect');
    assert_same('/', $result->location);
    assert_same(null, current_user(), 'session should be cleared after logout');
});
