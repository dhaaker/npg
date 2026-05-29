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

    <?php $errors = errors(); ?>

    <form method="post" action="/register">
        <?= csrf_field() ?>
        <p>
            <label>Email <input type="email" name="email" value="<?= e(old('email')) ?>" required></label>
            <?php foreach ($errors['email'] ?? [] as $message): ?>
                <span role="alert"><?= e($message) ?></span>
            <?php endforeach; ?>
        </p>
        <p>
            <label>Name <input type="text" name="name" value="<?= e(old('name')) ?>" required></label>
            <?php foreach ($errors['name'] ?? [] as $message): ?>
                <span role="alert"><?= e($message) ?></span>
            <?php endforeach; ?>
        </p>
        <p>
            <label>Password <input type="password" name="password" required minlength="8"></label>
            <?php foreach ($errors['password'] ?? [] as $message): ?>
                <span role="alert"><?= e($message) ?></span>
            <?php endforeach; ?>
        </p>
        <p>
            <label>Confirm password <input type="password" name="password_confirmation" required minlength="8"></label>
        </p>
        <button type="submit">Create account</button>
    </form>

    <p>Already have an account? <a href="/login">Log in</a>.</p>
</body>
</html>
