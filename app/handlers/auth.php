<?php

declare(strict_types=1);

// Example auth handlers. Each owns one URL and inspects $request->method itself
// (no method dispatch in the router). They return response *descriptions*; the
// runner performs the effect. CSRF is enforced globally by csrf_middleware, and
// form validation by validate() (lib/validation.php): a failed validate() throws
// a ValidationException that validation_middleware turns into a redirect back
// with errors + old input, so the POST branches below only see clean data.

function auth_register(Request $request): Html|Redirect
{
    if ($request->method === 'POST') {
        $data = validate($request->post, [
            'email' => 'required|email|max:255',
            'name' => 'required|max:100',
            'password' => 'required|min:8|confirmed',
        ]);

        if (query_one('SELECT id FROM users WHERE email = ?', [$data['email']]) !== null) {
            flash('error', 'That email is already registered.');

            return redirect('/register');
        }

        $user = create_user($data['email'], $data['name'], $data['password']);
        auth_login($user);
        flash('success', 'Welcome, ' . $data['name'] . '!');

        return redirect('/dashboard');
    }

    return html('auth/register');
}

function auth_signin(Request $request): Html|Redirect
{
    if ($request->method === 'POST') {
        $data = validate($request->post, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = auth_attempt($data['email'], $data['password']);
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
