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
├── lib/                   # the framework itself — global helper functions, one concern per file
│   ├── bootstrap.php      # load env, config, then require the rest of lib/
│   ├── env.php            # parse .env -> env()
│   ├── config.php         # config() over config array
│   ├── request.php        # Request object ($method, $path, $query, $post, $headers, ...)
│   ├── response.php       # Response value object + send(); response *descriptions* (Html/Json/Redirect)
│   │                      #   + helpers html()/json()/redirect()/not_found()/abort(); lower description -> Response
│   ├── router.php         # path() patterns, <int:id>/<slug> converters, match -> handler+params
│   ├── middleware.php     # onion runner around handler
│   ├── view.php           # render an Html description (extract context, include plain PHP template); e() escape helper
│   ├── db.php             # PDO bootstrap (single bootstrap-owned connection) + query/query_one/query_all + "last SQL" capture
│   ├── validation.php     # validate($input, $rules)
│   ├── auth.php           # sessions, csrf, password hashing, auth_* + current_user()
│   ├── errors.php         # error/exception handler, rich debug page, structured log
│   ├── migrate.php        # forward-only numbered .sql runner
│   └── vendor/            # hand-vendored single-file deps (rare; meets the vendoring bar)
├── app/
│   ├── handlers/          # handler functions, grouped by feature (e.g. users.php)
│   └── views/             # plain .php view files (no engine, nothing compiled)
├── routes.php             # the route table (one explicit table, pattern -> handler)
├── middleware.php         # ordered global middleware list
├── config.php             # returns config array (may read env())
├── migrations/            # 001_*.sql, 002_*.sql ...
├── storage/
│   └── logs/              # structured logs (gitignored)
├── tests/                 # plain PHP assertion files
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
- [ ] `lib/router.php`: `path($pattern, $handler)`; compile `<int:id>`, `<slug>`, `<str:name>` to a matcher; extract params in order.
- [ ] `routes.php` route table returning `path(...)` entries.
- [ ] Resolve handler name → callable (function in `app/handlers/`), call with `($request, ...$params)`.
- [ ] 404 when no route matches.
- **Check:** `/users/<int:id>` calls a handler receiving `$id`.

## Milestone 2 — Config & env
- [ ] `lib/env.php`: parse `.env` (KEY=VALUE, quotes, comments) → `env($key, $default)`.
- [ ] `lib/config.php`: load `config.php` array → `config('db.dsn')` dot access.
- [ ] `.env.example`, `config.php` with app + db keys.
- **Check:** `config()`/`env()` return expected values; missing keys behave sanely.

## Milestone 3 — Database (Postgres-first, raw SQL)
- [ ] `lib/db.php`: a **single bootstrap-owned PDO connection**, created lazily on first use from `config('db.dsn')`, with sane attributes (exceptions, assoc fetch). This is the one deliberate stateful global in the data layer — no per-call connection injection.
- [ ] `query($sql, $params)`, `query_one()`, `query_all()`, `last_insert_id()`, `tx(callable)`.
- [ ] Capture **last executed SQL + params** in a global for the debug page.
- **Check:** against local Postgres (`npgx` db) a SELECT round-trips to an array; tests swap the DSN to SQLite before the first query.

## Milestone 4 — Migrations + CLI seed
- [ ] `lib/migrate.php`: ensure `migrations` table; apply un-applied `migrations/NNN_*.sql` in order; record each.
- [ ] `npg` CLI: `serve`, `migrate` (parse argv, dispatch). Make executable.
- [ ] First migration `001_create_users.sql`.
- **Check:** `./npg migrate` creates tables; re-running is a no-op.

## Milestone 5 — Views (plain PHP, no engine, deferred render)
- [ ] `html($template, $context = [], $status = 200)` returns an `Html` description — it does **not** render on the spot.
- [ ] `lib/view.php`: the renderer the runner calls during lowering — extracts `Html.context` into scope, `include`s `app/views/$template.php`, captures output into the `Response` body. Rendering lives here / in the runner, never as a method on `Html`. This single deferred step is also where layout, flash messages, and CSRF token get injected into the context.
- [ ] `e()` escape helper (`htmlspecialchars`) for use in views; use native PHP control flow (`foreach`, `if`).
- **Check:** a handler returning `html(...)` renders via the runner with a loop + escaping; the returned `Html` still exposes `template`/`context`/`status` for tests to assert on before rendering; editing a template takes effect on next request — nothing to compile or cache.

## Milestone 6 — Middleware
- [ ] `lib/middleware.php`: onion runner `($request, $stack, $handler)`.
- [ ] `middleware.php` list; resolve named middleware + closures.
- **Check:** a logging middleware wraps every request; order is observable.

## Milestone 7 — Errors & dev experience
- [ ] `lib/errors.php`: set error/exception/shutdown handlers.
- [ ] Debug-mode page: message, source lines around fault, request context, **last SQL**. Plain/parseable.
- [ ] Prod mode: generic 500 + structured (JSON-line) log to `storage/logs/`.
- **Check:** a thrown error in debug shows source + last SQL; with `APP_DEBUG=false` shows generic page and logs.

## Milestone 8 — Validation
- [ ] `lib/validation.php`: `validate($input, $rules)`; rules `required|email|max:N|min:N|int|in:a,b|confirmed` (extensible).
- [ ] On failure: `ValidationException` → 422 / redirect-back-with-errors helper.
- **Check:** valid input returns clean data; invalid throws and is rendered/redirected.

## Milestone 9 — Auth (full batteries)
- [ ] Sessions (secure cookie settings) as middleware.
- [ ] CSRF: token in session, `csrf_field()`, verification middleware for unsafe methods.
- [ ] `auth_attempt($email,$pw)`, `auth_login($user)`, `logout()`, `current_user()`, `require_login($req)`.
- [ ] Password hashing wrappers (`password_hash`/`verify`).
- [ ] Migration for users; example login/logout handlers + views.
- **Check:** register → login → see current_user() → logout works; CSRF blocks forged POST.

## Milestone 10 — Scaffolding & tests
- [ ] `npg make:route` (handler stub + routes.php entry), `npg test` (tiny assertion runner).
- [~] `tests/` for router, views, validation, db (SQLite), auth.
- **Check:** `./npg test` is green; `make:route` produces a working route.

> Landed early (alongside Milestone 0): a tiny built-in test harness (`tests/harness.php` flat
> assertions + `tests/run.php` runner, discovers `tests/*_test.php`, exits non-zero on failure).
> Covered so far: response lowering (`to_response`/`html`/`json`/`redirect`) and
> `request_from_globals()`. Run with `php tests/run.php`; `npg test` will shell out to it once the
> CLI exists. Router/views/validation/db/auth tests still to come with their milestones.

## Milestone 11 — Demo app + docs
- [ ] A small CRUD feature (e.g. notes) exercising every subsystem end-to-end.
- [ ] README quickstart; keep `AGENTS.md` in sync with what was actually built.
- **Check:** clone → set `.env` → `./npg migrate` → `./npg serve` (or Herd at `npgx.test`) → working app, zero install step.

---

## Decisions locked in
- **Name:** framework + CLI = `npg`; repo/Herd site dir = `npgx` (served at `npgx.test`).
- **PHP:** 8.5 only — use current syntax freely.
- **Handlers return descriptions, the runner performs effects:** `html()`, `json()`, `redirect()`, `not_found()`, `abort()` each return a small immutable description object (named to match the helper: `html()` → `Html`); a single runner step lowers it into a `Response`. No type-sniffing — bare strings/arrays are rejected; a raw `Response` is the explicit low-level escape hatch. This split is the handler-testability seam.
- **Views:** plain PHP `.php` files, no template engine; rendering is **deferred** to the runner so the template name + context stay inspectable.
- **State is concentrated, not scattered:** one bootstrap-owned home each for the DB connection, session, and current request, reached via plain helpers; everything else prefers pure functions.
- **Serving:** Herd primary in dev (`npgx.test`); `./npg serve` (`php -S`) as the no-Herd fallback.
- **DB:** Postgres-first via `pdo_pgsql` (confirmed present); portable PDO so SQLite is usable for tests.
