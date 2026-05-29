<?php

declare(strict_types=1);

// View layer. `e()` is the pure escape helper every template uses.
// `render_html()` is the deferred renderer the runner calls during lowering
// (from to_response()): it extracts an Html description's context into scope and
// includes the plain PHP template, capturing its output as the response body.
// Rendering deliberately lives here as a function — never as a method on Html —
// so the template name and context stay inspectable until this single step runs.
// This single step is also where session-derived data (the current user, the
// CSRF token, and flash messages) is merged into the context, so every view can
// read it without each handler threading it through. No shared layout yet —
// views remain standalone documents.

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Context every view gets for free, merged underneath the handler's own context
 * in render_html(). Only computed when a session is active, so session-less
 * direct renders (e.g. in tests) stay untouched and never touch the DB.
 *
 * @return array<string, mixed>
 */
function view_shared_context(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }

    return [
        'current_user' => current_user(),
        'csrf_token' => csrf_token(),
        'flashes' => flashes(),
        'app' => config('app'),
    ];
}

function render_html(Html $html): string
{
    $templatePath = BASE_PATH . '/app/views/' . $html->template . '.php';

    if (!is_file($templatePath)) {
        throw new RuntimeException("View not found: {$html->template}");
    }

    // Handler context wins on key collisions, so a view can override anything
    // shared (e.g. pass its own `current_user`).
    $context = [...view_shared_context(), ...$html->context];
    extract($context, EXTR_SKIP);

    ob_start();
    include $templatePath;

    return (string) ob_get_clean();
}
