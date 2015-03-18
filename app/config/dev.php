<?php

use Monolog\Logger;

// re-use settings from prod
require __DIR__.'/prod.php';

// enable debug mode
$app['debug'] = true;

// MONOLOG
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../../app/storage/log/dev.log',
    'monolog.name'    => 'pid-app-dev',
    'monolog.level'   => Logger::DEBUG,
));