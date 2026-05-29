<?php

declare(strict_types=1);

// Example auth handlers. Each owns one URL and inspects $request->method itself
// (no method dispatch in the router). They return response *descriptions*; the
// runner performs the effect. CSRF is enforced globally by csrf_middleware, so
// the POST branches only need the form fields. The inline checks here are
// deliberately minimal and will be replaced by validate() in Milestone 9.

function auth_register(Request $request): Html|Redirect
{
    if ($request->method === 'POST') {
        $email = trim((string) ($request->post['email'] ?? ''));
        $name = trim((string) ($request->post['name'] ?? ''));
        $password = (string) ($request->post['password'] ?? '');

        // Minimal inline validation — replaced by validate() in M9.
        if ($email === '' || $name === '' || strlen($password) < 8) {
            flash('error', 'Email, name, and an 8+ character password are required.');

            return redirect('/register');
        }

        if (query_one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            flash('error', 'That email is already registered.');

            return redirect('/register');
        }

        $user = create_user($email, $name, $password);
        auth_login($user);
        flash('success', 'Welcome, ' . $name . '!');

        return redirect('/dashboard');
    }

    return html('auth/register');
}

function auth_signin(Request $request): Html|Redirect
{
    if ($request->method === 'POST') {
        $email = trim((string) ($request->post['email'] ?? ''));
        $password = (string) ($request->post['password'] ?? '');

        $user = auth_attempt($email, $password);
        if ($user === null) {
            flash('error', 'Those credentials do not match our records.');

            return redirect('/login');
        }

        auth_login($user);
        flash('success', 'Logged in.');

        return redirect('/dashboard');
    }

    return html('auth/signin');
}

function auth_logout(Request $request): Redirect
{
    logout();
    flash('success', 'Logged out.');

    return redirect('/');
}
