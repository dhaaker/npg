<?php

declare(strict_types=1);

// Error & dev experience. This file is deliberately self-contained: the debug
// page is built from plain strings with e() and never calls render_html() or a
// view template, so a broken template (or a dead DB) can't trigger a recursive
// failure while we're already reporting one. In debug mode we render a rich,
// plain/parseable page (message, source excerpt, request context, last SQL). In
// prod we render a generic 500 and append a structured JSON line to the log.

/**
 * Wire PHP's three failure channels to our handlers. Called once from the web
 * entry point (public/index.php); the test runner and CLI stay isolated.
 */
function install_error_handlers(): void
{
    set_error_handler('npg_error_handler');
    set_exception_handler('npg_exception_handler');
    register_shutdown_function('npg_shutdown_handler');
}

/**
 * Promote PHP warnings/notices to exceptions so they surface on the debug page
 * instead of being silently printed. Respects error_reporting() so anything
 * suppressed with '@' (or below the configured level) stays silent.
 */
function npg_error_handler(int $severity, string $message, string $file, int $line): bool
{
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
}

function npg_exception_handler(Throwable $e): void
{
    handle_throwable($e);
}

/**
 * Catch fatal errors (out of memory, parse, type errors that bypass the
 * exception handler) that PHP only reports at shutdown.
 */
function npg_shutdown_handler(): void
{
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if (($error['type'] & $fatal) === 0) {
        return;
    }

    handle_throwable(new ErrorException(
        $error['message'],
        0,
        $error['type'],
        $error['file'],
        $error['line'],
    ));
}

/**
 * The single place that decides debug-vs-prod and emits the 500. In debug we
 * render the rich page; in prod we log a structured line and show a generic
 * page that leaks nothing.
 */
function handle_throwable(Throwable $e): void
{
    $debug = (bool) config('app.debug', false);

    $body = $debug ? render_debug_page($e) : generic_error_page();

    if (!$debug) {
        log_error($e);
    }

    $response = new Response(
        status: 500,
        headers: ['Content-Type' => 'text/html; charset=UTF-8'],
        body: $body,
    );

    $response->send();
}

/**
 * Rich, plain/parseable HTML for the developer: exception class + message, the
 * source lines around the fault, the request context, the last SQL that ran,
 * and the stack trace. Everything dynamic is escaped via e().
 */
function render_debug_page(Throwable $e): string
{
    $class = $e::class;
    $message = $e->getMessage();
    $file = $e->getFile();
    $line = $e->getLine();

    $out = '<!DOCTYPE html>' . "\n";
    $out .= '<html lang="en"><head><meta charset="UTF-8">';
    $out .= '<title>' . e($class) . '</title>';
    $out .= '<style>body{font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;margin:2rem;color:#1a1a1a}'
        . 'h1{font-size:1.1rem;color:#b00020}h2{font-size:.9rem;margin-top:1.5rem;color:#555}'
        . 'pre{background:#f6f6f6;padding:1rem;overflow:auto;border-radius:4px}'
        . '.fault{background:#ffe0e0;display:block}table{border-collapse:collapse}'
        . 'td{padding:.1rem .75rem .1rem 0;vertical-align:top}</style>';
    $out .= '</head><body>';

    $out .= '<h1>' . e($class) . ': ' . e($message) . '</h1>';
    $out .= '<p>' . e($file) . ':' . $line . '</p>';

    $out .= '<h2>Source</h2><pre>';
    foreach (source_excerpt($file, $line) as $no => $text) {
        $row = str_pad((string) $no, 5, ' ', STR_PAD_LEFT) . ' | ' . $text;
        $row = e($row);
        $out .= $no === $line ? '<span class="fault">' . $row . "</span>\n" : $row . "\n";
    }
    $out .= '</pre>';

    $out .= '<h2>Request</h2><table>';
    foreach (request_debug_context() as $key => $value) {
        $out .= '<tr><td>' . e($key) . '</td><td>' . e($value) . '</td></tr>';
    }
    $out .= '</table>';

    $out .= '<h2>Last SQL</h2><pre>';
    $last = last_sql();
    if ($last === null) {
        $out .= '(no query ran)';
    } else {
        $out .= e($last['sql']) . "\n\nparams: " . e(json_encode($last['params'], JSON_THROW_ON_ERROR));
    }
    $out .= '</pre>';

    $out .= '<h2>Trace</h2><pre>' . e($e->getTraceAsString()) . '</pre>';

    $out .= '</body></html>';

    return $out;
}

/**
 * Read $line +/- $radius lines from $file as [lineNo => text]. Pure; returns an
 * empty array when the file can't be read so the debug page degrades gracefully.
 *
 * @return array<int, string>
 */
function source_excerpt(string $file, int $line, int $radius = 8): array
{
    if (!is_file($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }

    $total = count($lines);
    $start = max(1, $line - $radius);
    $end = min($total, $line + $radius);

    $excerpt = [];
    for ($i = $start; $i <= $end; $i++) {
        $excerpt[$i] = $lines[$i - 1];
    }

    return $excerpt;
}

/**
 * Request facts for the debug page, read straight from the superglobals so this
 * works no matter where in the lifecycle the throwable surfaced.
 *
 * @return array<string, string>
 */
function request_debug_context(): array
{
    $query = $_GET !== [] ? json_encode($_GET, JSON_THROW_ON_ERROR) : '';

    return [
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'query' => $query,
    ];
}

/**
 * Generic prod-mode 500: no internals, ever.
 */
function generic_error_page(): string
{
    return "<!DOCTYPE html>\n"
        . '<html lang="en"><head><meta charset="UTF-8"><title>Server Error</title></head>'
        . '<body><h1>500</h1><p>Something went wrong.</p></body></html>';
}

/**
 * Build a structured entry for a throwable and append it to the log.
 */
function log_error(Throwable $e): void
{
    $context = request_debug_context();

    log_line('error', $e->getMessage(), [
        'exception' => $e::class,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'method' => $context['method'],
        'uri' => $context['uri'],
        'last_sql' => last_sql(),
        'trace' => $e->getTraceAsString(),
    ]);
}

/**
 * Append one JSON object per line (JSONL) to the log. Each line is independently
 * parseable. The optional $path is the test seam; production logging goes to
 * error_log_path(). Creates the log directory if it does not exist yet.
 */
function log_line(string $level, string $message, array $context = [], ?string $path = null): void
{
    $path ??= error_log_path();

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $entry = [
        'time' => date('c'),
        'level' => $level,
        'message' => $message,
    ] + $context;

    file_put_contents(
        $path,
        json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX,
    );
}

function error_log_path(): string
{
    return BASE_PATH . '/storage/logs/app.log';
}
