<?php

declare(strict_types=1);

// The auth flow over the conventional `users` table: password hashing, the
// login attempt, and the session-backed identity helpers. Everything is a plain
// function over hand-written SQL (lib/db.php) and the session singleton
// (lib/session.php). current_user() reads the session, so it needs no request;
// require_login($request) is the explicit in-handler guard.

function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

function verify_password(string $plain, string $hash): bool
{
    return password_verify($plain, $hash);
}

/**
 * Insert a user with a hashed password and return the new row (without the
 * hash). Uses Postgres RETURNING so the insert and read are one round-trip.
 *
 * @return array{id: mixed, email: string, name: string}
 */
function create_user(string $email, string $name, string $password): array
{
    return query_one(
        'INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?) RETURNING id, email, name',
        [$email, $name, hash_password($password)],
    );
}

/**
 * Verify an email/password pair. Returns the user row (without password_hash)
 * on success, or null when the email is unknown or the password is wrong.
 *
 * @return array<string, mixed>|null
 */
function auth_attempt(string $email, string $password): ?array
{
    $user = query_one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($user === null || !verify_password($password, (string) $user['password_hash'])) {
        return null;
    }

    unset($user['password_hash']);

    return $user;
}

/**
 * Mark the given user as logged in for this session. Regenerates the session id
 * to prevent fixation, then records the user id and drops the cached identity.
 *
 * @param array<string, mixed> $user
 */
function auth_login(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $user['id'];
    unset($GLOBALS['__npg_current_user']);
}

/**
 * Log the current user out: forget the user id, regenerate the session id, and
 * clear the cached identity.
 */
function logout(): void
{
    unset($_SESSION['user_id']);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    unset($GLOBALS['__npg_current_user']);
}

/**
 * The logged-in user row, or null when nobody is logged in. Cached per request
 * so repeated calls (e.g. a guard plus the view) hit the DB only once.
 *
 * @return array<string, mixed>|null
 */
function current_user(): ?array
{
    if (array_key_exists('__npg_current_user', $GLOBALS)) {
        return $GLOBALS['__npg_current_user'];
    }

    $id = $_SESSION['user_id'] ?? null;
    $user = $id === null
        ? null
        : query_one('SELECT id, email, name, created_at FROM users WHERE id = ?', [$id]);

    $GLOBALS['__npg_current_user'] = $user;

    return $user;
}

/**
 * In-handler guard: returns a redirect to the login page when nobody is logged
 * in, or null when the request may proceed. Use as:
 *
 *     if ($r = require_login($request)) return $r;
 */
function require_login(Request $request): ?Redirect
{
    if (current_user() === null) {
        return redirect('/login');
    }

    return null;
}
