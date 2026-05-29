<?php

declare(strict_types=1);

// CLI scaffolding for the vendored-framework reuse model. `npg new` creates a
// fresh app and copies (vendors) this install's lib/ + npg into it; `npg update`
// re-copies lib/ into an existing app. Nothing here runs during a request — it
// is CLI-only tooling, kept beside migrate.php as the framework's build
// commands. There is no package manager: reuse is a plain file copy.

/**
 * Recursively copy a directory tree from $src to $dst, creating $dst as needed.
 */
function copy_dir(string $src, string $dst): void
{
    if (!is_dir($src)) {
        throw new RuntimeException("Cannot copy missing directory: {$src}");
    }

    if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
        throw new RuntimeException("Cannot create directory: {$dst}");
    }

    foreach (scandir($src) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $from = $src . '/' . $entry;
        $to = $dst . '/' . $entry;

        if (is_dir($from)) {
            copy_dir($from, $to);
        } else {
            copy_file($from, $to);
        }
    }
}

/**
 * Copy a single file, creating its parent directory first. Returns false if the
 * source file does not exist (the caller decides whether that is fatal).
 */
function copy_file(string $from, string $to): bool
{
    if (!is_file($from)) {
        return false;
    }

    write_dir(dirname($to));

    if (!copy($from, $to)) {
        throw new RuntimeException("Cannot copy {$from} -> {$to}");
    }

    return true;
}

function write_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: {$dir}");
    }
}

function write_new_file(string $path, string $contents): void
{
    write_dir(dirname($path));

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Cannot write file: {$path}");
    }
}

/**
 * Vendor the framework into an app: copy lib/npg/ and the npg CLI from
 * $sourceRoot into $targetRoot (npg made executable). The framework lives under
 * lib/npg/ so lib/vendor/ stays free for hand-vendored third-party deps. This
 * is the whole "install" — no package manager, just files.
 */
function vendor_framework(string $sourceRoot, string $targetRoot): void
{
    copy_dir($sourceRoot . '/lib/npg', $targetRoot . '/lib/npg');

    if (copy_file($sourceRoot . '/npg', $targetRoot . '/npg')) {
        chmod($targetRoot . '/npg', 0755);
    }
}

/**
 * Files copied verbatim from the source app because they are app-agnostic
 * batteries (generic entry point, config, middleware list, error views,
 * dotfiles). Paths are relative to the app root. The test runner/harness/support
 * are NOT here — they live in lib/npg/ and ride along via vendor_framework().
 * Migrations are NOT copied — a new app starts with an empty migrations/ and
 * writes its own schema.
 *
 * @return list<string>
 */
function scaffold_copied_files(): array
{
    return [
        'public/index.php',
        'config.php',
        'middleware.php',
        '.env.example',
        '.env.testing.example',
        '.gitignore',
        'app/views/_404.php',
        'app/views/_abort.php',
    ];
}

/**
 * Create a fresh, runnable npg app at $targetRoot, vendoring the framework from
 * $sourceRoot. Refuses to write into a non-empty directory. Returns the list of
 * top-level entries created, for the CLI to report.
 *
 * @return list<string>
 */
function scaffold_app(string $sourceRoot, string $targetRoot): array
{
    if (is_dir($targetRoot) && (scandir($targetRoot) ?: []) !== ['.', '..']) {
        throw new RuntimeException("Target directory is not empty: {$targetRoot}");
    }

    vendor_framework($sourceRoot, $targetRoot);

    foreach (scaffold_copied_files() as $relative) {
        copy_file($sourceRoot . '/' . $relative, $targetRoot . '/' . $relative);
    }

    // Starter app code (a single home route + an example test) — generated, not
    // copied, so a new app starts minimal instead of inheriting the demo's
    // handlers/views. The migrations/ dir starts empty (the app writes its own
    // schema); a .gitkeep keeps it in version control and makes `npg migrate` a
    // clean no-op.
    write_new_file($targetRoot . '/AGENTS.md', scaffold_agents_md_stub());
    write_new_file($targetRoot . '/routes.php', scaffold_routes_stub());
    write_new_file($targetRoot . '/app/handlers/home.php', scaffold_home_handler_stub());
    write_new_file($targetRoot . '/app/views/_header.php', scaffold_header_view_stub());
    write_new_file($targetRoot . '/app/views/_footer.php', scaffold_footer_view_stub());
    write_new_file($targetRoot . '/app/views/home.php', scaffold_home_view_stub());
    write_new_file($targetRoot . '/tests/home_test.php', scaffold_home_test_stub());
    write_new_file($targetRoot . '/migrations/.gitkeep', '');
    write_new_file($targetRoot . '/storage/logs/.gitkeep', '');

    return [
        'lib/', 'npg', 'AGENTS.md', 'public/index.php', 'config.php', 'routes.php',
        'middleware.php', 'app/', 'migrations/', 'storage/logs/', 'tests/',
        '.env.example',
    ];
}

/**
 * Re-copy the framework (lib/npg/) from $sourceRoot into an existing app at
 * $targetRoot — the "update the framework" story. The app's own code (and any
 * lib/vendor/ deps) is left untouched.
 */
function update_framework(string $sourceRoot, string $targetRoot): void
{
    if (!is_file($targetRoot . '/config.php')) {
        throw new RuntimeException("Not an npg app (no config.php): {$targetRoot}");
    }

    copy_dir($sourceRoot . '/lib/npg', $targetRoot . '/lib/npg');
}

/**
 * `npg make:route` — scaffold a handler stub for $pattern, wire it into
 * routes.php, and (unless $json) scaffold a matching view so the route works in
 * the browser immediately. Returns a list of human-readable change lines for
 * the CLI to print. Pure: every path is an explicit argument (the CLI passes
 * config('paths.*')), no global state.
 *
 * @return list<string>
 */
function make_route(
    string $pattern,
    string $handler,
    string $handlersDir,
    string $viewsDir,
    string $routesFile,
    bool $json = false,
): array {
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $handler)) {
        throw new InvalidArgumentException("Invalid handler name: {$handler} (must be a valid PHP function name)");
    }

    // Reuse the router's compiler: it validates the pattern (throws on an
    // unknown converter type) and hands back the typed params, so the generated
    // stub's argument list matches what dispatch() will pass in.
    [, $params] = compile_pattern($pattern);

    $handlerFile = $handlersDir . '/' . $handler . '.php';
    if (is_file($handlerFile)) {
        throw new RuntimeException("Handler file already exists: {$handlerFile}");
    }

    $changes = [];

    write_new_file($handlerFile, make_route_handler_stub($handler, $params, $json));
    $changes[] = "created  {$handlerFile}";

    if (!$json) {
        $viewFile = $viewsDir . '/' . $handler . '.php';
        if (is_file($viewFile)) {
            $changes[] = "skipped  {$viewFile} (already exists)";
        } else {
            write_new_file($viewFile, make_route_view_stub($handler, $params));
            $changes[] = "created  {$viewFile}";
        }
    }

    append_route($routesFile, $pattern, $handler);
    $changes[] = "updated  {$routesFile}";

    return $changes;
}

/**
 * Append a `path('<pattern>', '<handler>')` entry to the route table, just
 * before its closing `];`. Refuses to add a duplicate pattern. Assumes the
 * conventional `return [ ... ];` shape written by the scaffolder / demo app.
 */
function append_route(string $routesFile, string $pattern, string $handler): void
{
    if (!is_file($routesFile)) {
        throw new RuntimeException("Routes file not found: {$routesFile}");
    }

    $contents = (string) file_get_contents($routesFile);

    if (str_contains($contents, "path('{$pattern}'")) {
        throw new RuntimeException("Route already exists for pattern: {$pattern}");
    }

    $trimmed = rtrim($contents);
    if (!str_ends_with($trimmed, '];')) {
        throw new RuntimeException("Could not find the route table's closing '];' in {$routesFile}; add the route manually.");
    }

    $body = rtrim(substr($trimmed, 0, -2));
    $entry = "    path('{$pattern}', '{$handler}'),";

    write_new_file($routesFile, $body . "\n" . $entry . "\n];\n");
}

/**
 * The generated handler function. Path params become typed positional args
 * after $request (int -> int, slug/str -> string), matching what dispatch()
 * passes in, and are echoed back through the response so the route is useful
 * the moment it's created.
 *
 * @param list<array{name: string, type: string}> $params
 */
function make_route_handler_stub(string $handler, array $params, bool $json): string
{
    $args = ['Request $request'];
    foreach ($params as $param) {
        $args[] = route_param_php_type($param['type']) . ' $' . $param['name'];
    }
    $signature = implode(', ', $args);

    $returnType = $json ? 'Json' : 'Html';

    if ($json) {
        $items = ["        'ok' => true,"];
        foreach ($params as $param) {
            $items[] = "        '{$param['name']}' => \${$param['name']},";
        }
        $payload = "[\n" . implode("\n", $items) . "\n    ]";
        $body = "    return json({$payload});";
    } else {
        $items = [];
        foreach ($params as $param) {
            $items[] = "        '{$param['name']}' => \${$param['name']},";
        }
        $context = $items === [] ? '[]' : "[\n" . implode("\n", $items) . "\n    ]";
        $body = "    return html('{$handler}', {$context});";
    }

    return <<<PHP
<?php

declare(strict_types=1);

function {$handler}({$signature}): {$returnType}
{
{$body}
}

PHP;
}

/**
 * The generated view: a plain-PHP page wrapped in the shared header/footer
 * partials, echoing any path params with e(). Mirrors scaffold_home_view_stub().
 *
 * @param list<array{name: string, type: string}> $params
 */
function make_route_view_stub(string $handler, array $params): string
{
    $docblock = '';
    $lines = [];
    foreach ($params as $param) {
        $phpType = route_param_php_type($param['type']);
        $docblock .= "/** @var {$phpType} \${$param['name']} */\n";
        // e() is strictly typed (string); an int param must be cast or it throws
        // at render time. Mirrors the hand-written app/views/users/show.php.
        $expr = $phpType === 'int' ? "(string) \${$param['name']}" : "\${$param['name']}";
        $lines[] = "    <p>{$param['name']}: <?= e({$expr}) ?></p>";
    }

    $paramBlock = $lines === [] ? '' : "\n" . implode("\n", $lines);

    return <<<PHP
<?php

declare(strict_types=1);

{$docblock}?>
<?= partial('_header', ['title' => '{$handler}']) ?>
    <h1>{$handler}</h1>{$paramBlock}
<?= partial('_footer') ?>

PHP;
}

/**
 * Map a route converter type to the PHP type for a generated handler argument.
 * Only `int` is cast by the router; everything else arrives as a string.
 */
function route_param_php_type(string $type): string
{
    return $type === 'int' ? 'int' : 'string';
}

function scaffold_routes_stub(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

// The route table: one explicit pattern -> handler per entry. Handlers live in
// app/handlers/ and are plain functions. Routing does not branch on HTTP method
// — a handler owns a URL and inspects $request->method itself if it cares.

return [
    path('/', 'home'),
];

PHP;
}

function scaffold_home_handler_stub(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

function home(Request $request): Html
{
    return html('home', [
        'name' => config('app.name'),
    ]);
}

PHP;
}

function scaffold_home_view_stub(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

/** @var string $name */

?>
<?= partial('_header', ['title' => $name]) ?>
    <h1>Welcome to <?= e($name) ?></h1>
    <p>Edit <code>app/views/home.php</code> and refresh — nothing to compile.</p>
<?= partial('_footer') ?>

PHP;
}

function scaffold_header_view_stub(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

// Shared page header partial, rendered from a view with partial('_header', [...]).
// It receives whatever the caller passes plus the shared view context
// (current_user, csrf_token, flashes, app). Edit freely — it's plain PHP.

/** @var string $title */

$title ??= '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?></title>
</head>
<body>

PHP;
}

function scaffold_footer_view_stub(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

// Shared page footer partial, rendered with partial('_footer') to close out
// the document opened by _header.php.

?>
</body>
</html>

PHP;
}

function scaffold_home_test_stub(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

// Example test. The runner (lib/npg/run_tests.php) loads the framework but not
// your app/handlers/, so a handler test requires the file it exercises. A handler
// returns a response *description*, so the test asserts on that — no HTTP, no
// rendering, no DB.
require_once config('paths.handlers') . '/home.php';

test('home() describes the home view with the app name', function () {
    $home = home(new Request('GET', '/', [], [], [], ''));

    assert_true($home instanceof Html);
    assert_same('home', $home->template);
    assert_same(config('app.name'), $home->context['name']);
});

PHP;
}

function scaffold_agents_md_stub(): string
{
    return <<<'MD'
# AGENTS.md

Guidance for AI agents (and humans) building an app on **npg**.

## What this is

npg is a grug-brained, batteries-included PHP web framework. **No Composer, no
build step, no compilation, no codegen, no magic.** You edit PHP files under
`app/` and refresh the browser. Targets **PHP 8.5** — use current syntax freely.

The framework itself lives, vendored, under `lib/npg/` and ships the `npg` CLI.
**Do not edit `lib/npg/`** — it is replaced wholesale by `./npg update`. Your app
is everything else: `routes.php`, `app/`, `config.php`, `middleware.php`,
`migrations/`, `tests/`, and `.env`.

North star: a reader should understand any handler top-to-bottom with no hidden
lifecycle and no convention they must memorize first. Everything is an explicit,
grep-able function call.

## Project layout (what you touch)

```
routes.php          one explicit table: URL pattern -> handler name
app/handlers/       plain handler functions (one file per area)
app/views/          plain-PHP templates (.php), no template engine
migrations/         forward-only numbered NNN_*.sql
tests/              flat-assertion test files
config.php          returns a config array (reads .env via env())
middleware.php      ordered global middleware list
.env                secrets / per-environment values (copy from .env.example)
```

## Request flow

```
routes.php  ->  handler($request, ...$pathParams)  ->  return a response *description*  ->  runner renders & sends
```

- Register a route in `routes.php`: `path('/users/<int:id>', 'user_detail')`.
  Path converters: `int` (cast to int), `slug`, `str`. Bound segments are passed
  to the handler as positional args after `$request`.
- Routing does **not** branch on HTTP method — one handler owns a URL and
  inspects `$request->method` itself if it cares.
- A handler is a plain function. It receives the `Request` plus path params and
  **returns a value describing the response** — it never `echo`s and never
  renders.

```php
function user_detail(Request $request, int $id): Html|Redirect
{
    $user = query_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user) return not_found();

    if ($request->method === 'POST') {
        $data = validate($request->post, ['name' => 'required|max:100']);
        query('UPDATE users SET name = ? WHERE id = ?', [$data['name'], $id]);
        return redirect('/users/' . $id);
    }

    return html('users/show', ['user' => $user]);
}
```

## Response descriptions

Return exactly one of these (or a raw `Response` as the low-level escape hatch).
Bare strings/arrays are **errors**, not implicit HTML/JSON.

- `html('view/name', $context, $status = 200)` — renders `app/views/view/name.php`
- `json($data, $status = 200)`
- `redirect('/path', $status = 302)`
- `not_found()` — 404 via the `_404` view
- `abort($status, $message = '')` — error page via the `_abort` view

`Request` fields: `method`, `path`, `query`, `post`, `headers`, `body`.

## Views — plain PHP

Templates are ordinary `.php` files under `app/views/`. There is nothing to
compile or cache — what you write is what runs.

- Escape every dynamic value with `e()`: `<?= e($user['name']) ?>`.
- Compose with partials: `<?= partial('_header', ['title' => 'Home']) ?>`.
- Every view/partial gets shared context for free: `current_user`, `csrf_token`,
  `flashes`, and `app` (your `config('app')`). Your handler's context wins on key
  collisions.
- Put `<?= csrf_field() ?>` inside every non-GET `<form>`.
- Repopulate forms after a failed submit with `old('field')`; show validation
  errors with `errors()`.

## Data layer — raw parameterized SQL

No ORM, no query builder. Hand-written SQL over a shared PDO connection. **Always
bind params; never concatenate user input into SQL.** Rows are plain assoc arrays.

```php
query('INSERT INTO users (email, name) VALUES (?, ?)', [$email, $name]); // affected rows
$user  = query_one('SELECT * FROM users WHERE id = ?', [$id]);  // one row or null
$users = query_all('SELECT * FROM users WHERE active = ?', [1]); // array of rows
last_insert_id('users_id_seq'); // Postgres needs the sequence name
tx(fn() => /* ... */);          // run a closure in a transaction
```

## Auth

Sessions, CSRF, password hashing, and a login flow over a `users` table
(`id`, `email`, `name`, `password_hash`, `created_at`).

- `create_user($email, $name, $password)` — returns the new row (no hash)
- `auth_attempt($email, $password)` — user row on success, else null
- `auth_login($user)` / `logout()`
- `current_user()` — logged-in user row or null (cached per request)
- Guard inside a handler: `if ($r = require_login($request)) return $r;`

## Validation

`validate($input, $rules)` returns clean data (only declared keys; `int` coerced)
or throws. You don't catch it — `validation_middleware` turns a failure into a
422 JSON body for API clients, or a redirect back to the form with `errors()` and
`old()` flashed for the next request.

```php
$data = validate($request->post, [
    'email' => 'required|email',
    'name'  => 'required|max:100',
    'age'   => 'int|min:18',
]);
```

Rules: `required`, `email`, `int`, `max:N`, `min:N`, `in:a,b,c`, `confirmed`
(checks `{field}_confirmation`).

## Config & env

- `config('db.dsn')`, `config('app.name')`, `config('app.debug')` — read `config.php`
- `env('APP_DEBUG', 'false')` — read `.env`

## Migrations

Forward-only SQL files in `migrations/`, named `001_*.sql`, `002_*.sql`. To change
schema, write a new migration. Apply pending ones with `./npg migrate`.

## Middleware

`middleware.php` returns an ordered list run as an onion around every request.
Keep it to universal concerns (session, CSRF, validation). **Per-route concerns
are explicit guards inside handlers** (e.g. `require_login($request)`), not hidden
middleware.

## CLI

```
./npg serve          # dev server (php -S) with public/ as docroot
./npg migrate        # apply pending migrations
./npg test [files]   # run the test suite (optionally specific files)
./npg make:route <pattern> <handler> [--json]  # scaffold a handler + routes.php entry (and a view unless --json)
```

Nothing the CLI does is required for the app to run — it just bundles dev tasks.

## Testing

Tests are plain PHP files in `tests/`, run by `./npg test` (no PHPUnit, no
Composer). Because a handler returns a *description*, a test calls it and asserts
on the returned value — no HTTP, no rendering, no DB:

```php
require_once config('paths.handlers') . '/home.php';

test('home() describes the home view', function () {
    $home = home(new Request('GET', '/', [], [], [], ''));
    assert_true($home instanceof Html);
    assert_same('home', $home->template);
});
```

## House rules for editing this app

- No magic: if behavior isn't visible in the file you're reading (or one it
  explicitly requires/calls), it shouldn't happen.
- Explicit over implicit: prefer a plain function call the reader can see over a
  convention they must know.
- SQL stays SQL: always bind parameters, never string-concatenate user input.
- Handlers return a response description; they never `echo` and never render.
- Don't edit `lib/npg/` — that's the vendored framework. Build in `app/`.
- No Composer and no build step. If you think something must be vendored, it goes
  in `lib/vendor/` by hand — clear it with the maintainer first.
MD;
}
