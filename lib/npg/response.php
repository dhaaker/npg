<?php

declare(strict_types=1);

final readonly class Html
{
    public function __construct(
        public string $template,
        public array $context = [],
        public int $status = 200,
    ) {}
}

final readonly class Json
{
    public function __construct(
        public mixed $data,
        public int $status = 200,
    ) {}
}

final readonly class Redirect
{
    public function __construct(
        public string $location,
        public int $status = 302,
    ) {}
}

final class Response
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}

function html(string $template, array $context = [], int $status = 200): Html
{
    return new Html($template, $context, $status);
}

function json(mixed $data, int $status = 200): Json
{
    return new Json($data, $status);
}

function redirect(string $location, int $status = 302): Redirect
{
    return new Redirect($location, $status);
}

function not_found(): Html
{
    return new Html(
        template: '_404',
        context: ['message' => 'Not Found'],
        status: 404,
    );
}

function abort(int $status, string $message = ''): Html
{
    return new Html(
        template: '_abort',
        context: ['message' => $message, 'status' => $status],
        status: $status,
    );
}

function to_response(mixed $result): Response
{
    if ($result instanceof Response) {
        return $result;
    }

    if ($result instanceof Json) {
        return new Response(
            status: $result->status,
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($result->data, JSON_THROW_ON_ERROR),
        );
    }

    if ($result instanceof Redirect) {
        return new Response(
            status: $result->status,
            headers: ['Location' => $result->location],
            body: '',
        );
    }

    if ($result instanceof Html) {
        return new Response(
            status: $result->status,
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
            body: render_html($result),
        );
    }

    throw new InvalidArgumentException(
        'Handler must return a response description (Html, Json, Redirect) or a raw Response.',
    );
}
