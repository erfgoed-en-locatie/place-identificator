<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

// BOOTSTRAP this here app
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/routes.php';

// CONFIG
require_once __DIR__ . '/../app/config/parameters.php';

// DEV
$app->run();

// PROD
//$app['http_cache']->run();


