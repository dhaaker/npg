<?php

declare(strict_types=1);

/**
 * Parse .env file contents into key => value pairs.
 * Pure function — no global state.
 *
 * @return array<string, string>
 */
function parse_env(string $contents): array
{
    $vars = [];

    foreach (explode("\n", $contents) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));

        if ($key === '') {
            continue;
        }

        $value = unquote_env_value($value);
        $vars[$key] = $value;
    }

    return $vars;
}

function unquote_env_value(string $value): string
{
    $len = strlen($value);
    if ($len < 2) {
        return $value;
    }

    $first = $value[0];
    $last = $value[$len - 1];

    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
        return substr($value, 1, -1);
    }

    return $value;
}

function load_env(string $path): void
{
    if (!is_file($path)) {
        $GLOBALS['__npg_env'] = [];

        return;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        $GLOBALS['__npg_env'] = [];

        return;
    }

    $GLOBALS['__npg_env'] = parse_env($contents);
}

function env(string $key, mixed $default = null): mixed
{
    $store = $GLOBALS['__npg_env'] ?? [];

    if (array_key_exists($key, $store)) {
        return $store[$key];
    }

    $fromProcess = getenv($key);
    if ($fromProcess !== false) {
        return $fromProcess;
    }

    return $default;
}
