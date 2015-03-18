<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

// BOOTSTRAP this here app
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

// CONFIG
//require_once __DIR__ . '/../app/config/prod.php';
require_once __DIR__ . '/../app/config/dev.php';

// DEV
$app->run();

// PROD
//$app['http_cache']->run();

/** WHEN MOVING TO PROD:
 *
 * 1. change require statement above
 * 2. change the run mode (and use the cache)
 * 3. change database and mailer parameters in prod.php
 */

