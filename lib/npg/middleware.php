<?php

declare(strict_types=1);

// Onion-model middleware. A middleware is a plain callable
// `fn(Request $request, callable $next): mixed`: it may inspect the request,
// call $next($request) to reach the inner layers, inspect/replace the returned
// response description, or short-circuit by returning its own without calling
// $next. Both helpers here are pure — all inputs arrive as arguments.

function resolve_middleware(mixed $entry): callable
{
    if (!is_callable($entry)) {
        $name = is_string($entry) ? $entry : get_debug_type($entry);
        throw new RuntimeException("Middleware not callable: {$name}");
    }

    return $entry;
}

/**
 * Compose $stack around $core (the innermost call). The first stack entry is
 * the outermost layer, so it runs first on the way in and last on the way out.
 *
 * @param list<callable|string> $stack
 */
function run_middleware(Request $request, array $stack, callable $core): mixed
{
    $next = $core;

    foreach (array_reverse($stack) as $entry) {
        $middleware = resolve_middleware($entry);
        $next = static fn(Request $request): mixed => $middleware($request, $next);
    }

    return $next($request);
}
