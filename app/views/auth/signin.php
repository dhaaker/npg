<?php

declare(strict_types=1);

/** @var array<string, list<string>> $flashes */

$flashes ??= [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log in</title>
</head>
<body>
    <h1>Log in</h1>

    <?php foreach ($flashes['error'] ?? [] as $message): ?>
        <p role="alert"><?= e($message) ?></p>
    <?php endforeach; ?>

    <?php $errors = errors(); ?>

    <form method="post" action="/login">
        <?= csrf_field() ?>
        <p>
            <label>Email <input type="email" name="email" value="<?= e(old('email')) ?>" required></label>
            <?php foreach ($errors['email'] ?? [] as $message): ?>
                <span role="alert"><?= e($message) ?></span>
            <?php endforeach; ?>
        </p>
        <p>
            <label>Password <input type="password" name="password" required></label>
            <?php foreach ($errors['password'] ?? [] as $message): ?>
                <span role="alert"><?= e($message) ?></span>
            <?php endforeach; ?>
        </p>
        <button type="submit">Log in</button>
    </form>

    <p>Need an account? <a href="/register">Register</a>.</p>
</body>
</html>
