<?php

declare(strict_types=1);

/** @var int $id */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User <?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <h1>User <?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?></h1>
</body>
</html>
