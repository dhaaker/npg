<?php

declare(strict_types=1);

// This file owns the app root; the framework gets it via boot() and exposes the
// rest of the layout through config('paths.*'). public/ lives one level under
// the app root.
$appRoot = dirname(__DIR__);

require $appRoot . '/lib/bootstrap.php';

boot($appRoot);

install_error_handlers();

foreach (glob(config('paths.handlers') . '/*.php') ?: [] as $handlerFile) {
    require_once $handlerFile;
}

$routes = require config('paths.routes');
$middleware = require config('paths.middleware');
$request = request_from_globals();

$core = static fn(Request $request): mixed => dispatch($routes, $request);

to_response(run_middleware($request, $middleware, $core))->send();
