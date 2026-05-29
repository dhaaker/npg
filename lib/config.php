<?php

declare(strict_types=1);

/**
 * Dot-path access into a nested config array. Pure function.
 */
function config_get(array $config, string $key, mixed $default = null): mixed
{
    if ($key === '') {
        return $default;
    }

    $segments = explode('.', $key);
    $current = $config;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }

        $current = $current[$segment];
    }

    return $current;
}

function load_config(string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException("Config file not found: {$path}");
    }

    /** @var array<string, mixed> $config */
    $config = require $path;
    $GLOBALS['__npg_config'] = $config;
}

function config(string $key, mixed $default = null): mixed
{
    $store = $GLOBALS['__npg_config'] ?? [];

    return config_get($store, $key, $default);
}
