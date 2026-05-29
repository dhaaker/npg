<?php

declare(strict_types=1);

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $post,
        public array $headers,
        public string $body,
    ) {}
}

function request_from_globals(): Request
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';

    $headers = request_headers_from_server();

    $body = file_get_contents('php://input');
    if ($body === false) {
        $body = '';
    }

    return new Request(
        method: $method,
        path: $path,
        query: $_GET,
        post: $_POST,
        headers: $headers,
        body: $body,
    );
}

/**
 * Case-insensitive header lookup. HTTP header names are case-insensitive
 * (RFC 9110), but $request->headers is a plain array keyed by whatever casing
 * the client sent — getallheaders() does not normalise it — so a literal
 * $request->headers['X-Csrf-Token'] would miss a client that sent
 * x-csrf-token. Compare names case-insensitively here so every reader agrees.
 */
function request_header(Request $request, string $name, ?string $default = null): ?string
{
    foreach ($request->headers as $key => $value) {
        if (strcasecmp((string) $key, $name) === 0) {
            return is_string($value) ? $value : (string) $value;
        }
    }

    return $default;
}

function request_headers_from_server(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            return $headers;
        }
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        if (str_starts_with($key, 'HTTP_')) {
            $name = substr($key, 5);
        } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
            // These request headers arrive in $_SERVER without the HTTP_ prefix.
            $name = $key;
        } else {
            continue;
        }

        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
        $headers[$name] = is_string($value) ? $value : (string) $value;
    }

    return $headers;
}
