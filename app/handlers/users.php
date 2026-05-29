<?php

declare(strict_types=1);

function user_detail(Request $request, int $id): Html
{
    $user = query_one('SELECT id, name, email FROM users WHERE id = ?', [$id]);
    if ($user === null) {
        return not_found();
    }

    return html('users/show', ['user' => $user]);
}
