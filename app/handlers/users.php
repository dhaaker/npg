<?php

declare(strict_types=1);

function user_detail(Request $request, int $id): Html
{
    return html('users/show', ['id' => $id]);
}
