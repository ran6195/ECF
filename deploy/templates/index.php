<?php

declare(strict_types=1);

/**
 * Front controller di PRODUZIONE (collaudo edysma.net/testECF).
 *
 * Sta nella root della sottocartella, accanto all'admin (index.html, assets/)
 * e a embed.js. Il codice del backend (src, vendor, database, .env) vive nella
 * cartella ./app, protetta da accesso diretto (vedi app/.htaccess).
 *
 * Il base path dell'app è impostato via APP_BASE_PATH nel .env.
 */

require __DIR__ . '/app/vendor/autoload.php';

/** @var \Slim\App $app */
$app = require __DIR__ . '/app/src/bootstrap.php';

$app->run();
