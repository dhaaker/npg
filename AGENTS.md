# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## What this is

**npg** is a grug-brained, AI-optimized PHP web framework. The goal: **a batteries-included, high-productivity developer experience without the magic.** Routing, plain-PHP templating, a database layer, auth, validation, and migrations all ship in the box so you're productive on day one — but nothing is hidden: everything is explicit, flat, and discoverable by reading a single file. There is **no Composer, no build step, no compilation, no codegen**. You edit PHP files and refresh the browser.

Targets **PHP 8.5 only** — lean on current syntax freely.

Design north star: *a competent developer (or an AI) should be able to read any handler top-to-bottom and know exactly what happens — no reflection, no hidden lifecycle, no convention they have to memorize before the code makes sense.*

This does not mean *zero* global state — it means state is **concentrated, not scattered**. There is exactly one well-known, bootstrap-owned home for each piece of process-wide state (the DB connection, the session, the current request), reached through plain helper functions. We deliberately accept these few named singletons instead of threading them through every call or hiding them in a DI graph. The promise is not "nothing is global," it is "the global bits are tiny, obvious, and live in one predictable place." Everywhere else, prefer pure functions that take their inputs as arguments.

> Status: greenfield. As of this writing the repo contains only `public/index.php`. The architecture below is the agreed design to build toward — see `PLAN.md` for the build order. When you implement a piece, keep this file in sync.

## Hard rules (do not violate without explicit user sign-off)

- **No Composer / no package manager.** Dependencies are vendored by hand into `lib/vendor/` and `require`d directly.
- **No build/compile/transpile step, and no template engine.** Views are plain PHP files. There is nothing to compile, cache, or warm — what you write is what runs.
- **No magic.** No reflection-based DI container, no auto-wiring, no "fat model" ActiveRecord, no annotation routing, no facades. If behavior isn't visible in the file you're reading or the file it explicitly `require`s/calls, it shouldn't happen.
- **Explicit over implicit, always.** Prefer a plain function call the reader can see over a convention they must know.
- **SQL stays SQL.** No ORM, no query builder. Hand-written parameterized SQL via helpers.
- **Vendoring bar:** only vendor a dependency that is mature, widely used, has a small/inspectable dependency tree, and solves *real* protocol or domain complexity (e.g. a Postgres driver edge case, a robust mail/MIME encoder) — never for structural or cosmetic convenience we can write ourselves in a few readable functions.

## Core architecture

### Request lifecycle
`public/index.php` is the single front controller. Every request flows:

```
public/index.php
  -> require lib/npg/bootstrap.php (load framework helpers)
  -> boot($appRoot)                              // load env + config, register paths
  -> match URL against routes (config('paths.routes'))  // one explicit table, pattern -> handler
  -> run middleware stack (config('paths.middleware')) around the handler
  -> handler($request, ...$pathParams) returns a response description
  -> runner lowers it into a Response and sends it (status, headers, body)
```

The front controller owns the **app root** (`$appRoot = dirname(__DIR__)`) and passes it to `boot($appRoot)`. `boot()` is the one place the app's location enters the framework: it loads `.env` and `config.php` from the root and registers the filesystem layout (see *Config & paths* below). The framework lives under `lib/npg/` (leaving `lib/vendor/` for hand-vendored third-party deps) and contains **no** app-folder names or `BASE_PATH` — the same vendored `lib/npg/` runs in any app.

### Routing — one explicit table, no method dispatch
Routes live in a single explicit table (`routes.php`). A URL pattern maps to a handler. Path converters bind segments to handler arguments. **Routing does NOT branch on HTTP method** — one handler owns a URL and inspects `$request->method` itself if it cares (KISS).

```php
// routes.php
return [
    path('/',                 'home'),
    path('/users',            'users_index'),
    path('/users/<int:id>',   'user_detail'),   // id passed as 2nd handler arg
    path('/users/<slug>',     'user_by_slug'),
];
```

### Handlers — receive a request, return a response description
A handler is a plain function. It gets the `$request` plus any path params, and **returns a value that describes the response** (it does not echo, and it does not render). The helpers each name a *kind of response* — `html()`, `json()`, `redirect()`, `not_found()` — and return a small immutable description object. The runner lowers that description into a real `Response` and sends it.

There is no type-sniffing: a handler must return one of the explicit description objects, or a raw `Response` as the deliberate low-level escape hatch (custom headers, status, streaming). Bare strings and bare arrays are **not** accepted — returning one is an error, not silent HTML/JSON — because implicit "string means HTML, array means JSON" dispatch is exactly the kind of memorized convention this framework avoids, and a pre-rendered string throws away the inspectable `template`/`context`/`status` that make handlers testable.

This split — handler returns a description, runner performs the effect — is what makes handlers testable: a test calls the handler and asserts on the returned value (`$res->template`, `$res->context`, `$res->status`) without rendering HTML or buffering output.

```php
function user_detail($request, $id) {
    $user = query_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user) return not_found();

    if ($request->method === 'POST') {
        $data = validate($request->post, ['name' => 'required|max:100']);
        query('UPDATE users SET name = ? WHERE id = ?', [$data['name'], $id]);
        return redirect('/users/' . $id);
    }

    return html('users/show', ['user' => $user]);   // describes an HTML response; runner renders it
}
```

### Templating — plain PHP, no engine
Templates are ordinary `.php` files. `html('users/show', [...])` does **not** render on the spot — it returns an immutable `Html` description (template name, context, status). The runner renders it later in one place: it extracts the context array into scope and `include`s the file. Use PHP's own control flow and a tiny `e()` helper for escaping — no `{{ }}`, no directives, no compilation, no cache. What you write is what runs.

Because rendering is deferred to a single conversion step in the runner, the template name and context survive as inspectable data right up until they're rendered — that's the testability seam, and it's also where the layout, flash messages, and CSRF token get injected. The matching value object is a `final readonly class Html` with public `template`, `context`, and `status` fields and **no behavior** (the renderer is a separate function, never a method on the object). The `json()` and `redirect()` helpers return their own small description objects the same way.

```php
<h1><?= e($user['name']) ?></h1>
<?php foreach ($posts as $p): ?>
  <article><?= e($p['title']) ?></article>
<?php endforeach; ?>
```

### Data layer — raw parameterized SQL over PDO, Postgres-first
No ORM, no models. A thin set of helpers wraps PDO. SQL is written by hand; values are always bound. Rows come back as plain associative arrays.

```php
query('INSERT INTO users (email, name) VALUES (?, ?)', [$email, $name]);
$user  = query_one('SELECT * FROM users WHERE id = ?', [$id]);   // one row or null
$users = query_all('SELECT * FROM users WHERE active = ?', [1]); // array of rows
```

Postgres is the first-class target (`pdo_pgsql`), but helpers stay portable PDO so SQLite/MySQL work for tests.

The `query*` helpers reach a **single shared PDO connection** lazily created on first use and held in a bootstrap-owned singleton (see the north star note on concentrated state). This is the one bit of "spooky" global the data layer relies on — it is intentional, and it is the *only* such global here. There is no per-call connection injection. Tests run against a dedicated Postgres test database (the Laravel way): the test runner loads `.env.testing` so the configured DSN points at a `<db>_test` database, migrates it once, and resets state between tests by truncating tables.

### Config & paths — `.env` + `config.php`
`.env` holds secrets/per-environment values, parsed at boot. `config.php` returns a plain array (and may read from `env()`). Access via `config('db.dsn')` and `env('APP_DEBUG')`.

`config.php` also returns a `paths` block — the app's explicit filesystem map (`views`, `handlers`, `routes`, `middleware`, `migrations`, `logs`, ...), derived from `__DIR__` since `config.php` sits at the app root. The framework reads layout **only** through `config('paths.*')`, so `lib/npg/` never assumes a folder name. `boot()` merges these on top of `default_paths($appRoot)` (defined in `lib/npg/bootstrap.php`), so an app can omit a key and still boot, or override one to relocate a folder. This is what lets the same `lib/npg/` be vendored into any app — the app's layout is data in its own `config.php`, not a constant baked into the framework.

### Reuse — vendored framework, no package manager
npg is reused the same way it vendors third-party deps: **by copying files, never via Composer.** The framework unit is `lib/npg/` + the `npg` CLI; the app is everything else (`public/`, `app/`, `config.php`, `routes.php`, `middleware.php`, `migrations/`, `.env*`, `storage/`). The current repo doubles as the framework's home **and** its reference/demo app, so the framework is always exercised against something real.

- `./npg new <dir>` scaffolds a fresh, runnable app and vendors this install's `lib/npg/` + `npg` into it (a single home route, plus the app-agnostic batteries, and a `tests/` holding just an example handler test for `home` + `.env.testing.example`). The test runner/harness/`fresh_database()` live in the vendored `lib/npg/` (`run_tests.php`, `harness.php`, `support.php`), so the app never owns them. Zero install step — `cp .env.example .env`, `./npg migrate`, `./npg serve`; `cp .env.testing.example .env.testing` then `./npg test`.
- `./npg update <dir>` re-copies this install's `lib/npg/` over an existing app's `lib/npg/` (the "update the framework" path); the app's own code and any `lib/vendor/` deps are untouched.

The boundary is kept clean (no app assumptions in `lib/npg/`) precisely so the framework could later graduate to its own repo without further untangling.

### Middleware — one ordered list
`middleware.php` returns an ordered array of middleware run around every handler (onion model). Universal concerns only (session, CSRF, attaching the current user). Per-route concerns are explicit guards inside handlers (e.g. `require_login($request)`), not hidden middleware.

### Auth — full batteries, but plain
Ships sessions, CSRF protection, password hashing, and a login flow over a conventional `users` table: `auth_attempt()`, `auth_login()`, `current_user()`, `logout()`, `csrf_field()`. All are ordinary functions backed by SQL you can read.

### Errors — rich debug page in dev, generic in prod
In debug mode, uncaught errors render a readable page: exception message, source lines around the fault, the request context, and the **last SQL that ran**. Output is plain and parseable so an AI can diagnose from it directly. In production: a generic 500 plus a structured log entry. Never leak internals when `APP_DEBUG` is off.

### Migrations — forward-only numbered `.sql`
Plain SQL files in `migrations/` named `001_*.sql`, `002_*.sql`. The runner applies un-applied files in order and records them in a `migrations` table. Forward-only (write a new migration to change schema).

### Validation — plain rules array
`validate($input, ['email' => 'required|email', 'name' => 'required|max:100'])` returns clean data or throws a validation error the framework turns into a 422 / form redisplay.

## CLI — `./npg` (convenience only)
A single entry script. **Nothing it does is required for the app to run** — the app runs under any PHP server pointed at `public/`.

```
./npg serve          # php -S dev server with public/ as docroot
./npg migrate        # apply pending migrations
./npg new <dir>      # scaffold a new app, vendoring lib/npg/ + npg into it
./npg update <dir>   # re-copy this install's lib/npg/ into an existing app
./npg make:route     # scaffold a handler + routes.php entry
./npg test           # run the test suite
```

## Common commands

```bash
# Run locally — Herd serves it at npgx.test by default.
# Fallback (no Herd):
./npg serve                      # or: php -S localhost:8000 -t public

# Database
./npg migrate                    # apply pending migrations
psql "$DB_DSN"                   # direct Postgres access

# Tests
./npg test                       # whole suite
./npg test tests/router_test.php # single file
```

> Tests are plain PHP files run by a tiny built-in runner — no PHPUnit, no Composer. Keep tests as flat assertion functions.

## Conventions for writing code here

- One responsibility per `lib/npg/` file; a file should be readable in a single sitting.
- Helpers are **global functions** (`query`, `html`, `json`, `redirect`, `validate`, ...), not static methods on classes — this keeps call sites short and grep-able. (`query(` finds every DB call site; there is no method resolution or object graph to trace.)
- **Response helpers name the kind of response, and describe rather than render.** `html()`, `json()`, and `redirect()` each return a small immutable description object that the runner lowers into a `Response`; they perform no output themselves. Name the value object to match the function (`html()` → `Html`, etc.) for grep-ability, and keep rendering logic in the runner, never on the object.
- **Keep helpers pure wherever possible.** Functions like `e()`, `path()`, and `validate()` must take all their inputs as arguments and touch no global state — so the "just read the function" promise holds. Reserve dependence on a bootstrap singleton for the genuinely stateful helpers (DB connection, session, current request) and nothing else.
- **Pass request-scoped data explicitly.** Anything that depends on the current request should receive `$request` as an argument (as handlers and `require_login($request)` do) rather than reaching for a hidden global. If a helper like `current_user()` needs the request, the request it reads must be the one obvious bootstrap-owned global, not an ambient one constructed on the side.
- Handlers return a response *description* (`html()`, `json()`, `redirect()`, ...); they never `echo` and never render. The runner turns the description into a `Response`.
- Never build SQL by string concatenation with user input — always bind parameters.
- When adding a feature, ask first: *can a reader understand this without knowing a convention?* If not, make it explicit.
- Keep the framework dependency-free; if you think something must be vendored, clear it against the vendoring bar above with the user first.
