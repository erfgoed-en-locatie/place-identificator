<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();


// CONFIG
//require_once __DIR__ . '/../app/config/prod.php';
require_once __DIR__ . '/../app/config/dev.php';

// BOOTSTRAP this here app
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

// DEV
$app->run();

// PROD
//$app['http_cache']->run();
