<?php

declare(strict_types=1);

test('html() preserves template, context, and status as inspectable data', function () {
    $html = html('users/show', ['user' => ['id' => 7]], 201);

    assert_true($html instanceof Html);
    assert_same('users/show', $html->template);
    assert_same(['user' => ['id' => 7]], $html->context);
    assert_same(201, $html->status);
});

test('html() defaults to status 200 and empty context', function () {
    $html = html('home');

    assert_same([], $html->context);
    assert_same(200, $html->status);
});

test('to_response() rejects a bare string (no implicit "string means HTML")', function () {
    assert_throws(InvalidArgumentException::class, fn() => to_response('<h1>hi</h1>'));
});

test('to_response() rejects a bare array (no implicit "array means JSON")', function () {
    assert_throws(InvalidArgumentException::class, fn() => to_response(['ok' => true]));
});

test('to_response() rejects null', function () {
    assert_throws(InvalidArgumentException::class, fn() => to_response(null));
});

test('to_response() passes a raw Response through unchanged', function () {
    $response = new Response(204, ['X-Test' => '1'], '');

    assert_same($response, to_response($response));
});

test('to_response() lowers json() into an application/json Response', function () {
    $response = to_response(json(['ok' => true], 201));

    assert_true($response instanceof Response);
    assert_same(201, $response->status);
    assert_same('application/json', $response->headers['Content-Type']);
    assert_same('{"ok":true}', $response->body);
});

test('to_response() lowers redirect() into a Location Response with empty body', function () {
    $response = to_response(redirect('/users/7'));

    assert_same(302, $response->status);
    assert_same('/users/7', $response->headers['Location']);
    assert_same('', $response->body);
});

test('redirect() honours a custom status', function () {
    $response = to_response(redirect('/login', 303));

    assert_same(303, $response->status);
});

test('not_found() describes a 404 Html', function () {
    $html = not_found();

    assert_true($html instanceof Html);
    assert_same('_404', $html->template);
    assert_same(404, $html->status);
});

test('abort() carries the status into both the description and the response', function () {
    $response = to_response(abort(418, 'teapot'));

    assert_same(418, $response->status);
    assert_same('text/html; charset=UTF-8', $response->headers['Content-Type']);
    assert_true(str_contains($response->body, 'teapot'), 'rendered body should contain the abort message');
});

test('to_response() renders an Html description through the view layer', function () {
    $response = to_response(html('_404', ['message' => 'gone'], 404));

    assert_same(404, $response->status);
    assert_same('text/html; charset=UTF-8', $response->headers['Content-Type']);
    assert_true(str_contains($response->body, 'gone'), 'rendered body should contain the context message');
});
