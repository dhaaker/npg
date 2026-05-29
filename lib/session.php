<?php

declare(strict_types=1);

// The session is the second bootstrap-owned singleton (after the DB connection).
// All session state lives in PHP's $_SESSION superglobal and is reached only
// through the helpers here. Flash messages and the CSRF token piggyback on the
// same session. The two middleware functions at the bottom (session_middleware,
// csrf_middleware) are framework-owned and referenced by name from middleware.php.

/**
 * Start the session with secure cookie settings, then rotate flash messages so
 * anything flashed on the previous request is readable on this one. Idempotent:
 * a no-op if a session is already active.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = str_starts_with((string) config('app.url'), 'https://');

    session_set_cookie_params([
        'lifetime' => (int) config('session.lifetime', 0),
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);

    session_name((string) config('session.name', 'npg_session'));
    session_start();

    flash_rotate();
}

/**
 * Stash a message under $key to be shown on the *next* request (typically after
 * a redirect). Messages accumulate per key.
 */
function flash(string $key, string $message): void
{
    $_SESSION['_flash'][$key][] = $message;
}

/**
 * The flash messages carried over from the previous request, as
 * [key => list<string>]. Empty when nothing was flashed.
 *
 * @return array<string, list<string>>
 */
function flashes(): array
{
    return $GLOBALS['__npg_flash'] ?? [];
}

/**
 * Stash validation errors (field => list<string>) to be shown on the next
 * request, after a redirect back to the form. Read with errors().
 *
 * @param array<string, list<string>> $errors
 */
function flash_errors(array $errors): void
{
    $_SESSION['_flash_errors'] = $errors;
}

/**
 * Stash the submitted input so the redisplayed form can repopulate its fields.
 * Read per-field with old().
 *
 * @param array<string, mixed> $input
 */
function flash_old(array $input): void
{
    $_SESSION['_flash_old'] = $input;
}

/**
 * Validation errors carried over from the previous request, as
 * [field => list<string>]. Empty when the last request flashed none.
 *
 * @return array<string, list<string>>
 */
function errors(): array
{
    return $GLOBALS['__npg_errors'] ?? [];
}

/**
 * A single field's previously-submitted value, for repopulating a form after a
 * failed submit. Returns $default when there is no old input for $key.
 */
function old(string $key, string $default = ''): string
{
    $value = $GLOBALS['__npg_old'][$key] ?? $default;

    return is_string($value) ? $value : $default;
}

/**
 * Move last request's flashes, errors, and old input into the request-scoped
 * store and clear them from the session, so each is shown exactly once. Called
 * by start_session() on every request; exposed as a test seam for simulating
 * the redirect boundary.
 */
function flash_rotate(): void
{
    $GLOBALS['__npg_flash'] = $_SESSION['_flash'] ?? [];
    $GLOBALS['__npg_errors'] = $_SESSION['_flash_errors'] ?? [];
    $GLOBALS['__npg_old'] = $_SESSION['_flash_old'] ?? [];
    $_SESSION['_flash'] = [];
    $_SESSION['_flash_errors'] = [];
    $_SESSION['_flash_old'] = [];
}

/**
 * The per-session CSRF token, generated lazily on first use.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

/**
 * A hidden form input carrying the CSRF token. The token is hex, so it is safe
 * to emit raw in a template via <?= csrf_field() ?>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/**
 * Constant-time compare the submitted CSRF token (POST field _csrf, falling back
 * to the X-Csrf-Token header) against the session token.
 */
function csrf_verify(Request $request): bool
{
    if (empty($_SESSION['_csrf'])) {
        return false;
    }

    $submitted = $request->post['_csrf'] ?? $request->headers['X-Csrf-Token'] ?? '';
    if (!is_string($submitted) || $submitted === '') {
        return false;
    }

    return hash_equals($_SESSION['_csrf'], $submitted);
}

/**
 * Middleware: start the session around every request.
 */
function session_middleware(Request $request, callable $next): mixed
{
    start_session();

    return $next($request);
}

/**
 * Middleware: reject unsafe-method requests whose CSRF token is missing or wrong.
 * Safe methods (GET/HEAD/OPTIONS) pass through untouched.
 */
function csrf_middleware(Request $request, callable $next): mixed
{
    $unsafe = in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

    if ($unsafe && !csrf_verify($request)) {
        return abort(419, 'CSRF token mismatch');
    }

    return $next($request);
}

/**
 * Reset session state to a clean slate without a real PHP session — the test
 * seam that lets a test exercise the session/auth helpers (which only read and
 * write $_SESSION) without sending cookies or starting a file-backed session.
 */
function reset_session(): void
{
    $_SESSION = [];
    unset(
        $GLOBALS['__npg_flash'],
        $GLOBALS['__npg_errors'],
        $GLOBALS['__npg_old'],
        $GLOBALS['__npg_current_user'],
    );
}
