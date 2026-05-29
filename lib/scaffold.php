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
 * Vendor the framework into an app: copy lib/ and the npg CLI from $sourceRoot
 * into $targetRoot (npg made executable). This is the whole "install" — no
 * package manager, just files.
 */
function vendor_framework(string $sourceRoot, string $targetRoot): void
{
    copy_dir($sourceRoot . '/lib', $targetRoot . '/lib');

    if (copy_file($sourceRoot . '/npg', $targetRoot . '/npg')) {
        chmod($targetRoot . '/npg', 0755);
    }
}

/**
 * Files copied verbatim from the source app because they are app-agnostic
 * batteries (generic entry point, config, middleware list, error views, the
 * users migration, dotfiles). Paths are relative to the app root.
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
        '.gitignore',
        'migrations/001_create_users.sql',
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

    // Starter app code (a single home route) — generated, not copied, so a new
    // app starts minimal instead of inheriting the demo's handlers/views.
    write_new_file($targetRoot . '/routes.php', scaffold_routes_stub());
    write_new_file($targetRoot . '/app/handlers/home.php', scaffold_home_handler_stub());
    write_new_file($targetRoot . '/app/views/home.php', scaffold_home_view_stub());
    write_new_file($targetRoot . '/storage/logs/.gitkeep', '');

    return [
        'lib/', 'npg', 'public/index.php', 'config.php', 'routes.php',
        'middleware.php', 'app/', 'migrations/', 'storage/logs/', '.env.example',
    ];
}

/**
 * Re-copy the framework (lib/) from $sourceRoot into an existing app at
 * $targetRoot — the "update the framework" story. The app's own code is left
 * untouched.
 */
function update_framework(string $sourceRoot, string $targetRoot): void
{
    if (!is_file($targetRoot . '/config.php')) {
        throw new RuntimeException("Not an npg app (no config.php): {$targetRoot}");
    }

    copy_dir($sourceRoot . '/lib', $targetRoot . '/lib');
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($name) ?></title>
</head>
<body>
    <h1>Welcome to <?= e($name) ?></h1>
    <p>Edit <code>app/views/home.php</code> and refresh — nothing to compile.</p>
</body>
</html>

PHP;
}
