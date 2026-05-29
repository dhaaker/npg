<?php

declare(strict_types=1);

/** @var array{id: mixed, name: string, email: string} $user */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User <?= e((string) $user['id']) ?></title>
</head>
<body>
    <h1><?= e($user['name']) ?></h1>
    <p>User #<?= e((string) $user['id']) ?></p>
    <p><?= e($user['email']) ?></p>
</body>
</html>
