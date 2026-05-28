<?php

declare(strict_types=1);

final readonly class Route
{
    public function __construct(
        public string $pattern,
        public string $handler,
        public string $regex,
        /** @var list<array{name: string, type: string}> */
        public array $params,
    ) {}
}

function path(string $pattern, string $handler): Route
{
    [$regex, $params] = compile_pattern($pattern);

    return new Route($pattern, $handler, $regex, $params);
}

/**
 * @return array{0: string, 1: list<array{name: string, type: string}>}
 */
function compile_pattern(string $pattern): array
{
    $converters = route_converters();
    $params = [];
    $offset = 0;
    $regex = '';

    // Delimiter is '~' (not '/') because URL converter regexes naturally
    // contain '/' (e.g. the str type's [^/]+), which would otherwise collide
    // with a '/' delimiter and corrupt the compiled pattern.
    while (preg_match('/<(?:(\w+):)?(\w+)>/', $pattern, $matches, PREG_OFFSET_CAPTURE, $offset)) {
        $tokenStart = $matches[0][1];
        $literal = substr($pattern, $offset, $tokenStart - $offset);
        $regex .= preg_quote($literal, '~');

        $type = $matches[1][0] !== '' ? $matches[1][0] : 'str';
        $name = $matches[2][0];

        if (!isset($converters[$type])) {
            throw new InvalidArgumentException("Unknown route converter type: {$type}");
        }

        $params[] = ['name' => $name, 'type' => $type];
        $regex .= '(?P<' . $name . '>' . $converters[$type]['regex'] . ')';

        $offset = $tokenStart + strlen($matches[0][0]);
    }

    $regex .= preg_quote(substr($pattern, $offset), '~');
    $regex = '~^' . $regex . '$~';

    return [$regex, $params];
}

/**
 * @return array<string, array{regex: string, cast: ?callable}>
 */
function route_converters(): array
{
    return [
        'int' => [
            'regex' => '[0-9]+',
            'cast' => static fn(string $value): int => (int) $value,
        ],
        'slug' => [
            'regex' => '[a-z0-9]+(?:-[a-z0-9]+)*',
            'cast' => null,
        ],
        'str' => [
            'regex' => '[^/]+',
            'cast' => null,
        ],
    ];
}

/**
 * @return list<mixed>|null
 */
function match_route(Route $route, string $path): ?array
{
    if (!preg_match($route->regex, $path, $matches)) {
        return null;
    }

    $converters = route_converters();
    $params = [];

    foreach ($route->params as $param) {
        $name = $param['name'];
        $type = $param['type'];
        $value = $matches[$name];
        $cast = $converters[$type]['cast'];

        $params[] = $cast !== null ? $cast($value) : $value;
    }

    return $params;
}

function dispatch(array $routes, Request $request): mixed
{
    foreach ($routes as $route) {
        $params = match_route($route, $request->path);
        if ($params === null) {
            continue;
        }

        return call_handler($route->handler, $request, $params);
    }

    return not_found();
}

/**
 * @param list<mixed> $params
 */
function call_handler(string $handler, Request $request, array $params): mixed
{
    if (!is_callable($handler)) {
        throw new RuntimeException("Handler not found: {$handler}");
    }

    return $handler($request, ...$params);
}
