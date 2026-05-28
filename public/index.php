<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('LIB_PATH', BASE_PATH . '/lib');

require BASE_PATH . '/lib/bootstrap.php';

$request = request_from_globals();

$result = match ($request->path) {
    '/' => home($request),
    default => not_found(),
};

to_response($result)->send();

function home(Request $request): Html
{
    return html('home', [
        'name' => 'npg',
        'request_path' => $request->path,
    ]);
}
