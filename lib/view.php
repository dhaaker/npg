<?php

declare(strict_types=1);

// View layer. `e()` is the pure escape helper every template uses.
// `render_html()` is the deferred renderer the runner calls during lowering
// (from to_response()): it extracts an Html description's context into scope and
// includes the plain PHP template, capturing its output as the response body.
// Rendering deliberately lives here as a function — never as a method on Html —
// so the template name and context stay inspectable until this single step runs.
// Layout, flash messages, and CSRF token injection are deferred to their own
// milestones (session/auth) and intentionally not handled here yet.

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_html(Html $html): string
{
    $templatePath = BASE_PATH . '/app/views/' . $html->template . '.php';

    if (!is_file($templatePath)) {
        throw new RuntimeException("View not found: {$html->template}");
    }

    $context = $html->context;
    extract($context, EXTR_SKIP);

    ob_start();
    include $templatePath;

    return (string) ob_get_clean();
}
