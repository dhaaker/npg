<?php

declare(strict_types=1);

// Validation: the pure validate() rules plus validation_middleware, which turns
// a thrown ValidationException into a 422 (JSON) or a redirect-back-with-errors
// (HTML). The middleware tests use reset_session() — like auth_test.php — so the
// flash assertions run without a real cookie-backed session.

function valid_request(array $headers = []): Request
{
    return new Request(
        method: 'POST',
        path: '/register',
        query: [],
        post: [],
        headers: $headers,
        body: '',
    );
}

test('validate() returns only declared keys as clean data', function () {
    $clean = validate(
        ['email' => 'ada@example.com', 'name' => 'Ada', 'extra' => 'drop me'],
        ['email' => 'required|email', 'name' => 'required|max:100'],
    );

    assert_same(['email' => 'ada@example.com', 'name' => 'Ada'], $clean);
});

test('validate() coerces the int rule to a real int', function () {
    $clean = validate(['id' => '42'], ['id' => 'required|int']);

    assert_same(42, $clean['id']);
});

test('required fails on missing and empty values', function () {
    $e = assert_throws(ValidationException::class, function () {
        validate(['name' => ''], ['email' => 'required', 'name' => 'required']);
    });

    assert_true(isset($e->errors['email']), 'missing field should fail required');
    assert_true(isset($e->errors['name']), 'empty field should fail required');
});

test('an optional empty field passes and skips its other rules', function () {
    $clean = validate(['nickname' => ''], ['nickname' => 'email|max:5']);

    assert_same(['nickname' => ''], $clean);
});

test('email, in, and max rules reject bad input', function () {
    $e = assert_throws(ValidationException::class, function () {
        validate(
            ['email' => 'not-an-email', 'role' => 'root', 'bio' => 'way too long'],
            ['email' => 'required|email', 'role' => 'required|in:admin,user', 'bio' => 'required|max:4'],
        );
    });

    assert_true(isset($e->errors['email']), 'invalid email should fail');
    assert_true(isset($e->errors['role']), 'value outside in:list should fail');
    assert_true(isset($e->errors['bio']), 'overlong value should fail max');
});

test('min and confirmed guard a password field', function () {
    assert_throws(ValidationException::class, function () {
        validate(['password' => 'short'], ['password' => 'required|min:8']);
    });

    assert_throws(ValidationException::class, function () {
        validate(
            ['password' => 'supersecret', 'password_confirmation' => 'mismatch'],
            ['password' => 'required|min:8|confirmed'],
        );
    });

    $clean = validate(
        ['password' => 'supersecret', 'password_confirmation' => 'supersecret'],
        ['password' => 'required|min:8|confirmed'],
    );
    assert_same('supersecret', $clean['password']);
});

test('ValidationException carries every failure and the original input', function () {
    $input = ['email' => 'bad', 'name' => ''];

    $e = assert_throws(ValidationException::class, function () use ($input) {
        validate($input, ['email' => 'required|email', 'name' => 'required']);
    });

    assert_same($input, $e->input);
    assert_same(2, count($e->errors), 'one entry per failing field');
});

test('validation_middleware redirects back and flashes errors + old input', function () {
    reset_session();

    $next = function (Request $request) {
        return validate($request->post, ['email' => 'required|email']);
    };

    $result = validation_middleware(valid_request(), $next);

    assert_true($result instanceof Redirect, 'HTML clients should be redirected back');
    assert_same('/register', $result->location);

    // The errors/old input are flashed for the *next* request.
    flash_rotate();
    assert_true(isset(errors()['email']), 'errors should be flashed');
    assert_same('', old('email'), 'missing field has no old value');
});

test('validation_middleware returns a 422 JSON body for API clients', function () {
    reset_session();

    $next = fn (Request $request) => validate($request->post, ['email' => 'required|email']);

    $result = validation_middleware(valid_request(['accept' => 'application/json']), $next);

    assert_true($result instanceof Json, 'API clients should get a JSON description (Accept match is case-insensitive)');
    assert_same(422, $result->status);
    assert_true(isset($result->data['errors']['email']), 'errors should be in the JSON body');
});

test('validation_middleware passes a valid request straight through', function () {
    reset_session();

    $request = new Request(
        method: 'POST',
        path: '/register',
        query: [],
        post: ['email' => 'ada@example.com'],
        headers: [],
        body: '',
    );

    $next = fn (Request $request) => validate($request->post, ['email' => 'required|email']);

    assert_same(['email' => 'ada@example.com'], validation_middleware($request, $next));
});
