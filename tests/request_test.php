<?php

declare(strict_types=1);

test('request_from_globals() builds method, path, query, and post', function () {
    $savedServer = $_SERVER;
    $savedGet = $_GET;
    $savedPost = $_POST;

    try {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users/7?tab=posts';
        $_GET = ['tab' => 'posts'];
        $_POST = ['name' => 'Ada'];

        $request = request_from_globals();

        assert_true($request instanceof Request);
        assert_same('POST', $request->method);
        assert_same('/users/7', $request->path, 'path should be parsed out of the URI without the query string');
        assert_same(['tab' => 'posts'], $request->query);
        assert_same(['name' => 'Ada'], $request->post);
        assert_true(is_string($request->body), 'body should always be a string');
    } finally {
        $_SERVER = $savedServer;
        $_GET = $savedGet;
        $_POST = $savedPost;
    }
});

test('request_from_globals() defaults method to GET and path to / when server vars are absent', function () {
    $savedServer = $_SERVER;
    $savedGet = $_GET;
    $savedPost = $_POST;

    try {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        $_GET = [];
        $_POST = [];

        $request = request_from_globals();

        assert_same('GET', $request->method);
        assert_same('/', $request->path);
    } finally {
        $_SERVER = $savedServer;
        $_GET = $savedGet;
        $_POST = $savedPost;
    }
});

test('request_headers_from_server() normalises HTTP_* vars to canonical header names', function () {
    $saved = $_SERVER;

    try {
        $_SERVER = [
            'HTTP_HOST' => 'npgx.test',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];

        $headers = request_headers_from_server();

        assert_same('npgx.test', $headers['Host'] ?? null);
        assert_same('gzip', $headers['Accept-Encoding'] ?? null);
    } finally {
        $_SERVER = $saved;
    }
});

test('request_headers_from_server() includes CONTENT_TYPE and CONTENT_LENGTH (no HTTP_ prefix)', function () {
    $saved = $_SERVER;

    try {
        $_SERVER = [
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '42',
            'HTTP_HOST' => 'npgx.test',
        ];

        $headers = request_headers_from_server();

        assert_same('application/json', $headers['Content-Type'] ?? null, 'Content-Type must survive the fallback parser');
        assert_same('42', $headers['Content-Length'] ?? null, 'Content-Length must survive the fallback parser');
    } finally {
        $_SERVER = $saved;
    }
});

test('request_headers_from_server() skips non-header server vars', function () {
    $saved = $_SERVER;

    try {
        $_SERVER = [
            'HTTP_HOST' => 'npgx.test',
            'REQUEST_METHOD' => 'GET',
            'SERVER_PORT' => '443',
        ];

        $headers = request_headers_from_server();

        assert_same('npgx.test', $headers['Host'] ?? null);
        assert_true(!array_key_exists('Request-Method', $headers), 'REQUEST_METHOD is not a request header');
        assert_true(!array_key_exists('Server-Port', $headers), 'SERVER_PORT is not a request header');
    } finally {
        $_SERVER = $saved;
    }
});
