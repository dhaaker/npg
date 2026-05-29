# npg — Build Plan

A grug-brained, AI-optimized PHP web framework (framework + CLI: `npg`; repo/Herd site: `npgx` → `npgx.test`).
No Composer, no build step, no magic. **PHP 8.5 only.**
Batteries-included DX without the magic — routing, DB, auth, validation, and migrations out of the box, everything explicit. KISS. See `AGENTS.md` for the binding design rules.

This plan is ordered so the framework is **runnable as early as possible** and each milestone
ends with something you can see in a browser.

---

## Target directory layout

```
npgx/
├── public/
│   └── index.php          # front controller (the only web entry point)
├── lib/
│   ├── npg/               # the framework itself — global helper functions, one concern per file
│   │   ├── bootstrap.php  # require the rest of lib/npg/; boot($appRoot) loads env+config, registers paths
│   │   ├── env.php        # parse .env -> env()
│   │   ├── config.php     # config() over config array
│   │   ├── request.php    # Request object ($method, $path, $query, $post, $headers, ...)
│   │   ├── response.php   # Response value object + send(); response *descriptions* (Html/Json/Redirect)
│   │   │                  #   + helpers html()/json()/redirect()/not_found()/abort(); lower description -> Response
│   │   ├── router.php     # path() patterns, <int:id>/<slug> converters, match -> handler+params
│   │   ├── middleware.php # onion runner around handler
│   │   ├── view.php       # render an Html description (extract context, include plain PHP template); e() escape helper
│   │   ├── db.php         # PDO bootstrap (single bootstrap-owned connection) + query/query_one/query_all + "last SQL" capture
│   │   ├── validation.php # validate($input, $rules)
│   │   ├── auth.php       # sessions, csrf, password hashing, auth_* + current_user()
│   │   ├── errors.php     # error/exception handler, rich debug page, structured log
│   │   ├── migrate.php    # forward-only numbered .sql runner
│   │   ├── scaffold.php   # CLI-only: `npg new`/`update` vendor lib/npg/ + npg into an app (no package manager)
│   │   ├── harness.php    # test-only: test()/assert_* flat-assertion DSL (loaded by run_tests.php)
│   │   ├── support.php    # test-only: fresh_database() truncate-between-tests helper
│   │   └── run_tests.php  # test runner entry — spawned by `npg test`, boots .env.testing, runs tests/*_test.php
│   └── vendor/            # hand-vendored single-file third-party deps (rare; meets the vendoring bar)
├── app/
│   ├── handlers/          # handler functions, grouped by feature (e.g. users.php)
│   └── views/             # plain .php view files (no engine, nothing compiled)
├── routes.php             # the route table (one explicit table, pattern -> handler)
├── middleware.php         # ordered global middleware list
├── config.php             # returns config array (may read env())
├── migrations/            # 001_*.sql, 002_*.sql ...
├── storage/
│   └── logs/              # structured logs (gitignored)
├── tests/                 # plain PHP assertion files (*_test.php only; runner/harness live in lib/npg/)
├── .env                   # secrets / per-env (gitignored)
├── .env.example           # committed template
├── npg                    # CLI entry script (chmod +x)
├── AGENTS.md              # binding design rules (the north star)
└── PLAN.md                # this build plan
```

---

## Milestone 0 — Skeleton boots
*Goal: a request reaches a hardcoded handler that returns a response description, and the runner sends it.*
- [x] `public/index.php` front controller: define base paths, require `lib/bootstrap.php`, dispatch; the handler returns a **description**, the runner lowers it into a `Response` and sends it.
- [x] `lib/bootstrap.php`: require lib files in dependency order.
- [x] `lib/request.php`: build a `Request` from PHP superglobals (method, path, query, post, headers, raw body).
- [x] `lib/response.php`: `Response` value object (status, headers, body) + `send()`. Immutable description objects `Html`/`Json`/`Redirect` (`final readonly class`, plain fields, **no behavior**) with constructor helpers `html()`, `json()`, `redirect()`, `not_found()`, `abort()`. A single `to_response($result): Response` lowering step the runner calls — accepts a description or a raw `Response`; anything else (incl. bare string/array) is an error, **no type-sniffing**.
- [x] Minimal inline router stub so one route works end-to-end.
- **Check:** `php -S localhost:8000 -t public` → visiting `/` returns `html(...)` that the runner renders and sends.

## Milestone 1 — Routing (one explicit table)
*Goal: real URL table with path converters, no method dispatch.*
- [x] `lib/router.php`: `path($pattern, $handler)`; compile `<int:id>`, `<slug>`, `<str:name>` to a matcher; extract params in order.
- [x] `routes.php` route table returning `path(...)` entries.
- [x] Resolve handler name → callable (function in `app/handlers/`), call with `($request, ...$params)`.
- [x] 404 when no route matches.
- **Check:** `/users/<int:id>` calls a handler receiving `$id`.

## Milestone 2 — Config & env
- [x] `lib/env.php`: parse `.env` (KEY=VALUE, quotes, comments) → `env($key, $default)`.
- [x] `lib/config.php`: load `config.php` array → `config('db.dsn')` dot access.
- [x] `.env.example`, `config.php` with app + db keys.
- `env()` returns raw strings (trimmed, unquoted); bool/int coercion is explicit in `config.php`, not inside `env()`.
- **Check:** `config()`/`env()` return expected values; missing keys behave sanely.

## Milestone 3 — Database (Postgres-first, raw SQL)
- [x] `lib/db.php`: a **single bootstrap-owned PDO connection**, created lazily on first use from `config('db.dsn')`, with sane attributes (exceptions, assoc fetch). This is the one deliberate stateful global in the data layer — no per-call connection injection.
- [x] `query($sql, $params)`, `query_one()`, `query_all()`, `last_insert_id()`, `tx(callable)`.
- [x] Capture **last executed SQL + params** in a global for the debug page.
- **Check:** against local Postgres (`npgx` db) a SELECT round-trips to an array; tests run against a dedicated `_test` Postgres database.

> Landed in `lib/db.php` (required from `bootstrap.php`). Helpers: `db()` (lazy
> connection), `query`/`query_one`/`query_all`, `last_insert_id`, `tx`, plus a
> shared `db_execute()` that captures the last SQL+params (exposed via
> `last_sql()` for the Milestone 7 debug page) and `db_reset()` to drop the
> cached connection. Covered by `tests/db_test.php` against a dedicated Postgres
> test database (see the testing note under Milestone 10).

## Milestone 4 — Migrations + CLI seed
- [x] `lib/migrate.php`: ensure `migrations` table; apply un-applied `migrations/NNN_*.sql` in order; record each.
- [x] `npg` CLI: `serve`, `migrate` (parse argv, dispatch). Make executable.
- [x] First migration `001_create_users.sql`.
- **Check:** `./npg migrate` creates tables; re-running is a no-op.

> Landed in `lib/migrate.php` (required from `bootstrap.php`). Forward-only:
> `run_migrations($dir)` ensures the `migrations` table, then applies each
> pending `NNN_*.sql` in filename order inside `tx()` — migration SQL runs via
> `db()->exec()` (multi-statement) while the bookkeeping insert uses `query()`.
> Helpers `migration_files`/`applied_migrations`/`pending_migrations`/
> `apply_migration` take the dir as an explicit arg. `npg` CLI (executable, repo
> root) dispatches `serve` (php -S fallback) and `migrate`. First migration
> `migrations/001_create_users.sql` (Postgres). Covered by `tests/migrate_test.php`
> against the dedicated `_test` Postgres database (see the testing note under
> Milestone 10).

## Milestone 5 — Views (plain PHP, no engine, deferred render)
- [x] `html($template, $context = [], $status = 200)` returns an `Html` description — it does **not** render on the spot.
- [x] `lib/view.php`: the renderer the runner calls during lowering — extracts `Html.context` into scope, `include`s `app/views/$template.php`, captures output into the `Response` body. Rendering lives here / in the runner, never as a method on `Html`. This single deferred step is also where layout, flash messages, and CSRF token get injected into the context.
- [x] `e()` escape helper (`htmlspecialchars`) for use in views; use native PHP control flow (`foreach`, `if`). *(Landed early in `lib/view.php` — views were already being written in Milestone 1; the deferred renderer still to come here.)*
- **Check:** a handler returning `html(...)` renders via the runner with a loop + escaping; the returned `Html` still exposes `template`/`context`/`status` for tests to assert on before rendering; editing a template takes effect on next request — nothing to compile or cache.

> Landed in `lib/view.php`. The deferred renderer `render_html(Html): string`
> moved out of `lib/response.php` to sit beside the `e()` escape helper, keeping
> the view layer in one file; `to_response()` still calls it during lowering. It
> extracts the `Html` context (`EXTR_SKIP`) and `include`s `app/views/$template.php`
> under output buffering, throwing `RuntimeException` for a missing template.
> Rendering stays a plain function, never a method on `Html`, so template/context/
> status remain inspectable until this step. Covered by `tests/view_test.php`
> (direct render + missing-template throw) and end-to-end via `tests/response_test.php`.
> Layout, flash messages, and CSRF injection were intentionally deferred to their
> owning milestones (M6 middleware / M9 auth) — no shared layout yet, views stay
> standalone documents.

## Milestone 6 — Middleware
- [x] `lib/middleware.php`: onion runner `($request, $stack, $handler)`.
- [x] `middleware.php` list; resolve named middleware + closures.
- **Check:** a logging middleware wraps every request; order is observable.

> Landed in `lib/middleware.php` (required from `bootstrap.php` after `router.php`).
> A middleware is a plain callable `fn(Request $request, callable $next): mixed`.
> `run_middleware($request, $stack, $core)` composes the stack around `$core`
> via `array_reverse`, so the first list entry is the outermost layer (runs
> first inbound, last outbound); a middleware can short-circuit by returning a
> description without calling `$next`. `resolve_middleware()` accepts a closure
> as-is or a string function name (throws `RuntimeException` like `call_handler`
> if not callable). Both helpers are pure — all inputs are arguments. The
> ordered list lives in the repo-root `middleware.php` (mirroring `config.php`/
> `routes.php`), shipping a demo `log_requests` middleware. `public/index.php`
> wraps the whole dispatch (routing + handler + 404) — `$core = fn($request) =>
> dispatch($routes, $request)` — so logging covers 404s too. Covered by
> `tests/middleware_test.php` (empty stack, inbound/outbound order, short-circuit,
> name/closure resolution).

## Milestone 7 — Errors & dev experience
- [x] `lib/errors.php`: set error/exception/shutdown handlers.
- [x] Debug-mode page: message, source lines around fault, request context, **last SQL**. Plain/parseable.
- [x] Prod mode: generic 500 + structured (JSON-line) log to `storage/logs/`.
- **Check:** a thrown error in debug shows source + last SQL; with `APP_DEBUG=false` shows generic page and logs.

> Landed in `lib/errors.php` (required from `bootstrap.php`; handlers installed
> by `install_error_handlers()`, called once from `public/index.php` so the test
> runner and `npg` CLI stay isolated). `npg_error_handler` promotes
> warnings/notices to `ErrorException` (respecting `error_reporting()` for `@`
> suppression), `npg_exception_handler` catches uncaught throwables, and
> `npg_shutdown_handler` catches fatals via `error_get_last()`. All three funnel
> into `handle_throwable()`, which branches on `config('app.debug')`. The debug
> page (`render_debug_page`) is built from plain strings with `e()` — never
> `render_html()`/a view template, so a broken template or dead DB can't recurse
> — and shows the exception class+message, a `source_excerpt()` around the fault
> line, request context (read straight from superglobals via
> `request_debug_context()`), the `last_sql()` from `lib/db.php`, and the trace.
> Prod renders `generic_error_page()` (leaks nothing) and appends a structured
> JSONL entry via `log_error()`/`log_line()` to `error_log_path()`
> (`storage/logs/app.log`, dir auto-created; `?string $path` is the test seam).
> Covered by `tests/errors_test.php` (source excerpt window + unreadable file,
> debug page message/file/last-SQL, generic-page no-leak, JSONL append/parse).

## Milestone 8 — Auth (full batteries)
- [x] Sessions (secure cookie settings) as middleware.
- [x] CSRF: token in session, `csrf_field()`, verification middleware for unsafe methods.
- [x] `auth_attempt($email,$pw)`, `auth_login($user)`, `logout()`, `current_user()`, `require_login($req)`.
- [x] Password hashing wrappers (`password_hash`/`verify`).
- [x] Migration for users; example login/logout handlers + views.
- **Check:** register → login → see current_user() → logout works; CSRF blocks forged POST.

> Split into `lib/session.php` + `lib/auth.php` (one responsibility per file).
> The session is the second bootstrap-owned singleton (after the DB connection),
> reached only through `lib/session.php`: `start_session()` (secure cookie
> params — `httponly`, `samesite=Lax`, `secure` when `app.url` is https — driven
> by a new `config('session.*')` block), `flash`/`flashes`/`flash_rotate` (a
> flash set before a `redirect()` survives exactly one request via a
> request-scoped `$GLOBALS['__npg_flash']`), and the CSRF token store
> (`csrf_token`, `csrf_field`, `csrf_verify` with `hash_equals`). Two
> framework-owned middleware live here and are listed by name in the repo-root
> `middleware.php`: `session_middleware` (outermost) then `csrf_middleware`
> (rejects unsafe-method requests with `abort(419)` on a missing/forged token).
> `lib/auth.php` holds the flow over the existing `users` table:
> `hash_password`/`verify_password` (PASSWORD_DEFAULT), `create_user` (INSERT …
> RETURNING), `auth_attempt` (returns the row without `password_hash`),
> `auth_login` (regenerates the session id), `logout`, `current_user` (reads the
> session, cached per request — no request arg), and `require_login($request)`
> (returns a `Redirect` to `/login` or null). The deferred renderer now merges
> `view_shared_context()` (current user, CSRF token, flashes, app config) under
> the handler's own context — computed only when a session is active, so
> session-less direct renders (and `view_test.php`) are untouched. No new
> migration: `001_create_users.sql` already suffices and PHP file sessions need
> no table. Example `app/handlers/auth.php` — page handlers are `auth_`-prefixed
> (`auth_register`/`auth_signin`/`auth_logout`; `auth_signin` rather than
> `auth_login` to avoid colliding with the `auth_login()` helper) — plus the
> login-protected `dashboard` handler in its own `app/handlers/dashboard.php`, and
> standalone `auth/register`, `auth/signin`, and `dashboard` views. Covered by
> `tests/auth_test.php` (hashing, `auth_attempt` success/failure, CSRF
> pass/mismatch, login→`current_user`→logout, `require_login` guard, flash
> rotation) — tests use a `reset_session()` seam (named to avoid the PHP builtin
> `session_reset()`) so the suite needs no real cookie-backed session. Verified
> end-to-end over `php -S`: register→dashboard (302), forged POST→419,
> logout→redirect, logged-out dashboard→`/login`.

## Milestone 9 — Validation
- [x] `lib/validation.php`: `validate($input, $rules)`; rules `required|email|max:N|min:N|int|in:a,b|confirmed` (extensible).
- [x] On failure: `ValidationException` → 422 / redirect-back-with-errors helper.
- **Check:** valid input returns clean data; invalid throws and is rendered/redirected.

> Landed in `lib/validation.php` (required from `bootstrap.php` after
> `session.php`, before `auth.php`). `validate($input, $rules)` is a **pure**
> function — all inputs are arguments, no global state — that returns clean data
> (only the declared keys, with the `int` rule coerced to a real int) or throws
> `ValidationException` carrying per-field messages (`errors`) and the original
> `input` (for old()). Rules are a flat dispatch (`validation_passes()` predicate
> + `validation_message()` text, both `match` on the rule name, so `grep` finds
> each one): `required`, `email`, `max:N`/`min:N` (string length via `mb_strlen`),
> `int`, `in:a,b,c`, `confirmed` (reads `{field}_confirmation`). An empty,
> non-required field is optional — it passes through and its other rules are
> skipped. All side effects of a failure live in the framework-owned
> `validation_middleware` (listed by name in the repo-root `middleware.php` after
> `session`/`csrf`, so flashing works): it catches `ValidationException` and
> returns `json(['errors' => …], 422)` for `Accept: application/json` clients, or
> flashes errors + old input and redirects back to `$request->path` for forms
> (handlers own one URL for GET+POST). The redirect payload rides the existing
> rotate-once flash seam: new `flash_errors`/`flash_old` setters and
> `errors()`/`old($key)` readers in `lib/session.php`, rotated by an extended
> `flash_rotate()` and cleared by `reset_session()`. Views call `errors()`/
> `old()` directly (like `csrf_field()`), so no context threading. Example
> handlers now use `validate()`: `auth_register`
> (`email|name|password+confirmed`) and `auth_signin`; their views redisplay
> field errors and repopulate old input, and register gained a
> `password_confirmation` field. Covered by `tests/validation_test.php` (clean
> data / key filtering, int coercion, every rule pass+fail, optional-empty skip,
> exception payload, and the middleware's redirect-with-flash / 422-JSON /
> pass-through branches).

## Milestone 10 — Scaffolding & tests
- [x] `npg make:route` (handler stub + routes.php entry).
- [x] `npg test` (tiny assertion runner) — shells out to the framework runner `lib/npg/run_tests.php`, forwards optional file args.
- [x] `tests/` for router, views, validation (Milestone 9), db (dedicated `_test` Postgres DB), auth (router covered in Milestone 1).
- **Check:** `./npg test` is green; `make:route` produces a working route.

> Landed early (alongside Milestone 0): a tiny built-in test harness (flat
> assertions + a runner that discovers `tests/*_test.php` and exits non-zero on
> failure). The harness, support, and runner were later relocated into the
> framework (`lib/npg/harness.php`, `lib/npg/support.php`, `lib/npg/run_tests.php`)
> so an app's `tests/` holds only its own `*_test.php` files.
> Covered so far: response lowering (`to_response`/`html`/`json`/`redirect`),
> `request_from_globals()`, and routing (`path`/`compile_pattern`/`match_route`/`dispatch`).
> Run with `./npg test` (or directly: `php lib/npg/run_tests.php`).
> Views/validation/auth tests landed with their milestones; `tests/session_test.php`
> closes the last gap by covering `lib/session.php` directly — `csrf_field()`,
> `csrf_middleware` (safe-method pass-through, `abort(419)` on a missing/forged
> token, pass on a valid one), `session_middleware`, flash accumulation per key,
> the `flash_errors`/`flash_old` → `errors()`/`old()` rotation, and
> `reset_session()` clearing. The whole suite (97 assertions) is green. The
> `npg test` subcommand has since landed (shells out to `lib/npg/run_tests.php`),
> and `npg make:route` (below) closes the milestone.
>
> Database tests run against a dedicated Postgres test database, the Laravel way:
> `lib/npg/run_tests.php` loads `.env.testing` (instead of `.env`) so `config('db.dsn')`
> points at the `_test` database, refuses to run unless the db name contains
> `test`, then migrates it once up front. Each DB test calls `fresh_database()`
> (`lib/npg/support.php`) to TRUNCATE every table except `migrations` and start
> clean. Set up locally with `createdb npg_test` and a `.env.testing` copied from
> `.env.testing.example`.
>
> `npg make:route <pattern> <handler> [--json]` closes the milestone. It lives in
> `lib/npg/scaffold.php` (beside `scaffold_app`/`update_framework`) as pure
> functions the CLI feeds `config('paths.*')` into — mirroring how `migrate`
> calls `run_migrations(config('paths.migrations'))`. `make_route()` validates
> the handler name, reuses the router's `compile_pattern()` to validate the
> pattern and recover its typed params (so the generated stub's args match what
> `dispatch()` passes — `int` -> `int`, `slug`/`str` -> `string`), writes
> `app/handlers/<handler>.php` (refusing to clobber), and — unless `--json` — a
> matching `app/views/<handler>.php` so the route renders in the browser
> immediately. `append_route()` inserts a `path('<pattern>', '<handler>')` entry
> before the route table's closing `];` (refusing duplicate patterns). With
> `--json` the handler returns `json([...])` and no view is written. Covered by
> `tests/scaffold_test.php` (append insertion + duplicate guard, html/json
> variants, typed-param stubs, unknown-converter / invalid-name / clobber errors).

## Milestone 11 — Demo app + docs
- [ ] A small CRUD feature (e.g. notes) exercising every subsystem end-to-end.
- [ ] README quickstart; keep `AGENTS.md` in sync with what was actually built.
- **Check:** clone → set `.env` → `./npg migrate` → `./npg serve` (or Herd at `npgx.test`) → working app, zero install step.

## Reusability — vendored framework (Option B)
*Goal: the same framework runs in any app; reuse is a file copy, not a package install.*
- [x] Decoupled `lib/npg/` from app layout: it no longer references `BASE_PATH` or any app-folder name. The front controller / CLI / test runner own the app root and call `boot($appRoot)` (in `lib/npg/bootstrap.php`), which loads env + config and registers paths.
- [x] Paths are data, not constants: `config.php` returns a `paths` block (derived from `__DIR__`); `default_paths()` + `register_paths()` merge app overrides over framework defaults; `lib/npg/view.php` and `lib/npg/errors.php` read `config('paths.*')`.
- [x] Framework lives under `lib/npg/` (so `lib/vendor/` stays free for hand-vendored third-party deps). `lib/npg/scaffold.php` + `npg new <dir>` / `npg update <dir>`: vendor `lib/npg/` + `npg` by copying. `new` scaffolds a runnable starter (home route + app-agnostic batteries) into an empty dir; `update` re-copies `lib/npg/` into an existing app.
- [x] `npg new` scaffolds a `tests/` holding only an example handler test (`tests/home_test.php`, asserting `home()` returns the expected `Html`) plus `.env.testing.example`; the DB-backed runner + harness + `fresh_database()` ship inside the vendored `lib/npg/` (`run_tests.php`, `harness.php`, `support.php`), so apps never own or edit them. `./npg test` runs the suite. Migrations are not copied — a new app starts with an empty `migrations/`.
- **Check:** `./npg new /tmp/app` → `cd /tmp/app` → serves `/` (200) with vendored `lib/npg/`, zero install step; the boundary stays clean so a later split into its own repo (Option C) is trivial.

> Landed: `lib/npg/` is now layout-agnostic. `boot(string $appRoot, ?string $envPath = null)`
> in `lib/npg/bootstrap.php` is the single seam where an app's location enters the
> framework — it loads `.env` (or a passed env file, used by `lib/npg/run_tests.php`
> for `.env.testing`), loads `config.php`, then `register_paths()` merges the app's
> `config('paths')` over `default_paths($appRoot)`. `lib/npg/view.php` resolves
> templates via `config('paths.views')` and `lib/npg/errors.php` logs to
> `config('paths.logs')`; neither touches `BASE_PATH` (kept only as an app-side
> convenience in the entry points/test files). The framework files moved under
> `lib/npg/`, leaving `lib/vendor/` for third-party deps. Reuse follows the
> framework's own "vendor by copying" rule: `lib/npg/scaffold.php` provides
> `copy_dir`/`vendor_framework`/`scaffold_app`/`update_framework`, wired into the
> `npg` CLI as `new`/`update`. Verified: full suite green (97 assertions) after the
> refactor, and a freshly scaffolded app serves `/` end-to-end with no Composer/build step.

---

## Decisions locked in
- **Name:** framework + CLI = `npg`; repo/Herd site dir = `npgx` (served at `npgx.test`).
- **PHP:** 8.5 only — use current syntax freely.
- **Handlers return descriptions, the runner performs effects:** `html()`, `json()`, `redirect()`, `not_found()`, `abort()` each return a small immutable description object (named to match the helper: `html()` → `Html`); a single runner step lowers it into a `Response`. No type-sniffing — bare strings/arrays are rejected; a raw `Response` is the explicit low-level escape hatch. This split is the handler-testability seam.
- **Views:** plain PHP `.php` files, no template engine; rendering is **deferred** to the runner so the template name + context stay inspectable.
- **State is concentrated, not scattered:** one bootstrap-owned home each for the DB connection, session, and current request, reached via plain helpers; everything else prefers pure functions.
- **Serving:** Herd primary in dev (`npgx.test`); `./npg serve` (`php -S`) as the no-Herd fallback.
- **DB:** Postgres-first via `pdo_pgsql` (confirmed present); portable PDO so SQLite is usable for tests.
