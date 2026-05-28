<?php

declare(strict_types=1);

/** @var string $name */
/** @var string $request_path */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?> is alive</h1>
    <p>Request path: <?= htmlspecialchars($request_path, ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>
