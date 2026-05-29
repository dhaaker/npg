<?php

declare(strict_types=1);

/** @var array{id: mixed, name: string, email: string} $user */
/** @var array<string, list<string>> $flashes */

$flashes ??= [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>
<body>
    <h1>Dashboard</h1>

    <?php foreach ($flashes['success'] ?? [] as $message): ?>
        <p role="status"><?= e($message) ?></p>
    <?php endforeach; ?>

    <p>Signed in as <?= e($user['name']) ?> (<?= e($user['email']) ?>).</p>

    <form method="post" action="/logout">
        <?= csrf_field() ?>
        <button type="submit">Log out</button>
    </form>
</body>
</html>
