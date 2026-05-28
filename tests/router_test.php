<?php

declare(strict_types=1);

function router_test_request(string $path, string $method = 'GET'): Request
{
    return new Request($method, $path, [], [], [], '');
}

test('compile_pattern() anchors a literal route and records no params', function () {
    [$regex, $params] = compile_pattern('/');

    assert_same('~^/$~', $regex);
    assert_same([], $params);
});

test('compile_pattern() records int param type and name', function () {
    [$regex, $params] = compile_pattern('/users/<int:id>');

    assert_same('~^/users/(?P<id>[0-9]+)$~', $regex);
    assert_same([['name' => 'id', 'type' => 'int']], $params);
});

test('match_route() matches a static path', function () {
    $route = path('/', 'home');

    assert_same([], match_route($route, '/'));
    assert_same(null, match_route($route, '/nope'));
});

test('match_route() extracts int id and rejects non-numeric segments', function () {
    $route = path('/users/<int:id>', 'user_detail');

    assert_same([7], match_route($route, '/users/7'));
    assert_same(null, match_route($route, '/users/abc'));
});

test('match_route() accepts slug segments and rejects invalid slugs', function () {
    $route = path('/posts/<slug:handle>', 'post_by_slug');

    assert_same(['my-post'], match_route($route, '/posts/my-post'));
    assert_same(null, match_route($route, '/posts/My_Post'));
});

test('match_route() preserves param order for multiple converters', function () {
    $route = path('/a/<int:x>/b/<slug:y>', 'multi');

    assert_same([3, 'my-post'], match_route($route, '/a/3/b/my-post'));
});

test('compile_pattern() defaults a bare <name> to the str converter', function () {
    [$regex, $params] = compile_pattern('/files/<name>');

    assert_same('~^/files/(?P<name>[^/]+)$~', $regex);
    assert_same([['name' => 'name', 'type' => 'str']], $params);
});

test('match_route() captures a bare <name> as an unmodified string', function () {
    $route = path('/files/<name>', 'file_detail');

    assert_same(['report.pdf'], match_route($route, '/files/report.pdf'));
    assert_same(null, match_route($route, '/files/a/b'), 'str must not cross a path separator');
});

test('compile_pattern() throws for unknown converter types', function () {
    assert_throws(InvalidArgumentException::class, fn() => compile_pattern('/x/<bogus:name>'));
});

test('dispatch() calls the matching handler with request and path params', function () {
    $routes = [path('/echo/<int:id>', 'router_test_echo_handler')];
    $request = router_test_request('/echo/42');

    $result = dispatch($routes, $request);

    assert_true($result instanceof Html);
    assert_same('echo', $result->template);
    assert_same(['id' => 42, 'path' => '/echo/42'], $result->context);
});

test('dispatch() returns not_found() when no route matches', function () {
    $routes = [path('/', 'home')];
    $request = router_test_request('/missing');

    $result = dispatch($routes, $request);

    assert_true($result instanceof Html);
    assert_same('_404', $result->template);
    assert_same(404, $result->status);
});

test('call_handler() throws when the handler name is not callable', function () {
    $request = router_test_request('/');

    assert_throws(RuntimeException::class, fn() => call_handler('router_test_missing_handler_xyz', $request, []));
});

function router_test_echo_handler(Request $request, int $id): Html
{
    return html('echo', ['id' => $id, 'path' => $request->path]);
}
