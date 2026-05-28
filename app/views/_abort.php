<?php

declare(strict_types=1);

/** @var int $status */
/** @var string $message */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Error <?= (int) $status ?></title>
</head>
<body>
    <h1><?= (int) $status ?></h1>
    <?php if ($message !== ''): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</body>
</html>
