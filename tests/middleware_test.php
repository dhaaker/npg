<?php

declare(strict_types=1);

function middleware_test_request(string $path = '/', string $method = 'GET'): Request
{
    return new Request($method, $path, [], [], [], '');
}

test('run_middleware() returns the core result unchanged with an empty stack', function () {
    $request = middleware_test_request();
    $core = static fn(Request $request): mixed => html('home', ['path' => $request->path]);

    $result = run_middleware($request, [], $core);

    assert_true($result instanceof Html);
    assert_same('home', $result->template);
    assert_same(['path' => '/'], $result->context);
});

test('run_middleware() runs layers outside-in inbound and inside-out outbound', function () {
    $log = [];
    $outer = function (Request $request, callable $next) use (&$log): mixed {
        $log[] = 'outer:in';
        $result = $next($request);
        $log[] = 'outer:out';

        return $result;
    };
    $inner = function (Request $request, callable $next) use (&$log): mixed {
        $log[] = 'inner:in';
        $result = $next($request);
        $log[] = 'inner:out';

        return $result;
    };
    $core = function (Request $request) use (&$log): mixed {
        $log[] = 'core';

        return json(['ok' => true]);
    };

    run_middleware(middleware_test_request(), [$outer, $inner], $core);

    assert_same(['outer:in', 'inner:in', 'core', 'inner:out', 'outer:out'], $log);
});

test('run_middleware() lets a middleware short-circuit without calling the core', function () {
    $coreRan = false;
    $guard = static fn(Request $request, callable $next): mixed => redirect('/login');
    $core = function (Request $request) use (&$coreRan): mixed {
        $coreRan = true;

        return html('home');
    };

    $result = run_middleware(middleware_test_request(), [$guard], $core);

    assert_true($result instanceof Redirect);
    assert_same('/login', $result->location);
    assert_same(false, $coreRan, 'core must not run when a middleware short-circuits');
});

test('resolve_middleware() passes a closure through unchanged', function () {
    $closure = static fn(Request $request, callable $next): mixed => $next($request);

    assert_same($closure, resolve_middleware($closure));
});

test('resolve_middleware() resolves a string function name to a callable', function () {
    $resolved = resolve_middleware('middleware_test_passthrough');

    assert_true(is_callable($resolved));
    assert_same('middleware_test_passthrough', $resolved);
});

test('resolve_middleware() throws for an unknown function name', function () {
    assert_throws(RuntimeException::class, fn() => resolve_middleware('middleware_test_missing_xyz'));
});

function middleware_test_passthrough(Request $request, callable $next): mixed
{
    return $next($request);
}
