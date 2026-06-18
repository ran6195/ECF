<?php

declare(strict_types=1);

// Front controller. Funziona sia con Apache (.htaccess) sia con il server
// integrato di PHP (php -S ... public/index.php).

// Con il built-in server, lascia che i file statici esistenti (es. embed.js)
// vengano serviti direttamente senza passare da Slim.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

/** @var \Slim\App $app */
$app = require __DIR__ . '/../src/bootstrap.php';

$app->run();
