<?php

declare(strict_types=1);

// The ordered global middleware list, run as an onion around every request
// (see lib/middleware.php). The first entry is the outermost layer. Entries
// are named functions (grep-able) or closures — both resolve to a callable
// `fn(Request $request, callable $next): mixed`.
//
// Example — request logging (define the function in this file, then add its name):
//
//     return [
//         'log_requests',
//     ];
//
//     function log_requests(Request $request, callable $next): mixed
//     {
//         $result = $next($request);
//         error_log(sprintf('[npg] %s %s', $request->method, $request->path));
//
//         return $result;
//     }
//
// session_middleware must come before csrf_middleware (CSRF reads the session)
// and before any handler that calls current_user(); both are defined in
// lib/session.php.

return [
    'session_middleware',
    'csrf_middleware',
];
