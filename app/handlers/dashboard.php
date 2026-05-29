<?php

declare(strict_types=1);

// The dashboard: a login-protected page. It guards with require_login() (the
// explicit in-handler guard, not hidden middleware) and renders the current
// user, which the deferred renderer also exposes to every view via shared
// context.

function dashboard(Request $request): Html|Redirect
{
    if ($r = require_login($request)) {
        return $r;
    }

    return html('dashboard', ['user' => current_user()]);
}
