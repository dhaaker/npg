<?php

declare(strict_types=1);

// View helpers. Pure functions only — they take their input as arguments and
// touch no global state, so a reader can understand them in isolation.
// The deferred renderer (Milestone 5) will also live here; for now this is the
// escape helper that every template uses.

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
