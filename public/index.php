<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('LIB_PATH', BASE_PATH . '/lib');

require BASE_PATH . '/lib/bootstrap.php';

install_error_handlers();

foreach (glob(BASE_PATH . '/app/handlers/*.php') ?: [] as $handlerFile) {
    require_once $handlerFile;
}

$routes = require BASE_PATH . '/routes.php';
$middleware = require BASE_PATH . '/middleware.php';
$request = request_from_globals();

$core = static fn(Request $request): mixed => dispatch($routes, $request);

to_response(run_middleware($request, $middleware, $core))->send();
