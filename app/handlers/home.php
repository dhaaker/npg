<?php

declare(strict_types=1);

function home(Request $request): Html
{
    return html('home', [
        'name' => config('app.name'),
        'request_path' => $request->path,
    ]);
}
