<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/request.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/db.php';

load_env(BASE_PATH . '/.env');
load_config(BASE_PATH . '/config.php');
