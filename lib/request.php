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
