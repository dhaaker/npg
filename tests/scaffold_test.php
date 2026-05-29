<?php

declare(strict_types=1);

// Covers `npg make:route` (lib/npg/scaffold.php). Everything happens in a
// throwaway dir under sys_get_temp_dir() — handlers/views/routes are written
// there, never into the real app — and is cleaned up in finally.

function make_scaffold_dir(): string
{
    $dir = sys_get_temp_dir() . '/npg_scaffold_test_' . uniqid('', true);
    mkdir($dir);
    mkdir($dir . '/handlers');
    mkdir($dir . '/views');

    file_put_contents($dir . '/routes.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    path('/', 'home'),
];

PHP);

    return $dir;
}

function remove_scaffold_dir(string $dir): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

test('append_route() inserts a new entry before "];" and keeps existing routes', function () {
    $dir = make_scaffold_dir();

    try {
        append_route($dir . '/routes.php', '/posts', 'posts_index');

        $contents = (string) file_get_contents($dir . '/routes.php');

        assert_true(str_contains($contents, "path('/', 'home'),"), 'existing route preserved');
        assert_true(str_contains($contents, "    path('/posts', 'posts_index'),\n];\n"), 'new route inserted before close');
    } finally {
        remove_scaffold_dir($dir);
    }
});

test('append_route() refuses a duplicate pattern', function () {
    $dir = make_scaffold_dir();

    try {
        assert_throws(RuntimeException::class, function () use ($dir) {
            append_route($dir . '/routes.php', '/', 'another_home');
        });
    } finally {
        remove_scaffold_dir($dir);
    }
});

test('append_route() throws on a missing routes file', function () {
    assert_throws(RuntimeException::class, function () {
        append_route('/no/such/routes.php', '/x', 'x');
    });
});

test('make_route() default writes a handler + view and appends the route', function () {
    $dir = make_scaffold_dir();

    try {
        $changes = make_route('/posts', 'posts_index', $dir . '/handlers', $dir . '/views', $dir . '/routes.php');

        assert_true(is_file($dir . '/handlers/posts_index.php'), 'handler written');
        assert_true(is_file($dir . '/views/posts_index.php'), 'view written');
        assert_same(3, count($changes), 'reports handler + view + routes');

        $handler = (string) file_get_contents($dir . '/handlers/posts_index.php');
        assert_true(str_contains($handler, 'function posts_index(Request $request): Html'), 'html return type');
        assert_true(str_contains($handler, "return html('posts_index', []);"), 'empty html context');

        $routes = (string) file_get_contents($dir . '/routes.php');
        assert_true(str_contains($routes, "path('/posts', 'posts_index'),"), 'route appended');
    } finally {
        remove_scaffold_dir($dir);
    }
});

test('make_route() --json writes a json handler and no view', function () {
    $dir = make_scaffold_dir();

    try {
        $changes = make_route('/api/ping', 'api_ping', $dir . '/handlers', $dir . '/views', $dir . '/routes.php', true);

        assert_true(is_file($dir . '/handlers/api_ping.php'), 'handler written');
        assert_true(!is_file($dir . '/views/api_ping.php'), 'no view written');
        assert_same(2, count($changes), 'reports handler + routes only');

        $handler = (string) file_get_contents($dir . '/handlers/api_ping.php');
        assert_true(str_contains($handler, 'function api_ping(Request $request): Json'), 'json return type');
        assert_true(str_contains($handler, "'ok' => true,"), 'json ok payload');
    } finally {
        remove_scaffold_dir($dir);
    }
});

test('make_route() types path params from the pattern (int -> int, slug/str -> string)', function () {
    $dir = make_scaffold_dir();

    try {
        make_route('/posts/<int:id>/<slug>', 'post_show', $dir . '/handlers', $dir . '/views', $dir . '/routes.php');

        $handler = (string) file_get_contents($dir . '/handlers/post_show.php');
        assert_true(
            str_contains($handler, 'function post_show(Request $request, int $id, string $slug): Html'),
            'typed args in signature',
        );
        assert_true(str_contains($handler, "'id' => \$id,"), 'int param passed to context');
        assert_true(str_contains($handler, "'slug' => \$slug,"), 'slug param passed to context');

        $view = (string) file_get_contents($dir . '/views/post_show.php');
        assert_true(str_contains($view, '<?= e((string) $id) ?>'), 'int param cast for strict e()');
        assert_true(str_contains($view, '<?= e($slug) ?>'), 'string param echoed without a cast');
    } finally {
        remove_scaffold_dir($dir);
    }
});

test('make_route() rejects an unknown converter type', function () {
    $dir = make_scaffold_dir();

    try {
        assert_throws(InvalidArgumentException::class, function () use ($dir) {
            make_route('/posts/<foo:x>', 'bad_conv', $dir . '/handlers', $dir . '/views', $dir . '/routes.php');
        });
    } finally {
        remove_scaffold_dir($dir);
    }
});

test('make_route() rejects an invalid handler name', function () {
    $dir = make_scaffold_dir();

    try {
        assert_throws(InvalidArgumentException::class, function () use ($dir) {
            make_route('/posts', '9bad-name', $dir . '/handlers', $dir . '/views', $dir . '/routes.php');
        });
    } finally {
        remove_scaffold_dir($dir);
    }
});

test('make_route() refuses to clobber an existing handler file', function () {
    $dir = make_scaffold_dir();

    try {
        file_put_contents($dir . '/handlers/posts_index.php', '<?php // existing');

        assert_throws(RuntimeException::class, function () use ($dir) {
            make_route('/posts', 'posts_index', $dir . '/handlers', $dir . '/views', $dir . '/routes.php');
        });
    } finally {
        remove_scaffold_dir($dir);
    }
});
