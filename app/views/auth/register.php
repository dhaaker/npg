<?php

declare(strict_types=1);

/** @var array<string, list<string>> $flashes */

$flashes ??= [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
</head>
<body>
    <h1>Register</h1>

    <?php foreach ($flashes['error'] ?? [] as $message): ?>
        <p role="alert"><?= e($message) ?></p>
    <?php endforeach; ?>

    <form method="post" action="/register">
        <?= csrf_field() ?>
        <p>
            <label>Email <input type="email" name="email" required></label>
        </p>
        <p>
            <label>Name <input type="text" name="name" required></label>
        </p>
        <p>
            <label>Password <input type="password" name="password" required minlength="8"></label>
        </p>
        <button type="submit">Create account</button>
    </form>

    <p>Already have an account? <a href="/login">Log in</a>.</p>
</body>
</html>
