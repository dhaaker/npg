<?php

declare(strict_types=1);

/** @var string $name */
/** @var string $request_path */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($name) ?></title>
</head>
<body>
    <h1><?= e($name) ?> is alive</h1>
    <p>Request path: <?= e($request_path) ?></p>
</body>
</html>
