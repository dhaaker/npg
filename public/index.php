<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('LIB_PATH', BASE_PATH . '/lib');

require BASE_PATH . '/lib/bootstrap.php';

foreach (glob(BASE_PATH . '/app/handlers/*.php') ?: [] as $handlerFile) {
    require_once $handlerFile;
}

$routes = require BASE_PATH . '/routes.php';
$request = request_from_globals();

to_response(dispatch($routes, $request))->send();
