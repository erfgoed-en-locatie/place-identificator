<?php

// SITE
use Monolog\Logger;

$app['sitename'] = 'E&L - Plaatsnamen standaardiseren';
$app['upload_dir'] = __DIR__ . '/../storage/uploads';

// Locale
$app['locale'] = 'nl';
$app['session.default_locale'] = $app['locale'];


// DOCTRINE DBAL
$app["db.options"] = array(
    'driver'   => 'pdo_mysql',
    'host'     => 'localhost',
    'user'     => 'xxx',
    'password' => 'xxx',
    'dbname'   => 'xxx',
    'charset'   => 'utf8',
);

// MAILER
$app['swiftmailer.options'] = array(
    'host' => 'smtp.gmail.com',
    'port' => 465,
    'username' => 'histograph.io@gmail.com',
    'password' => 'xxx',
    'encryption' => 'ssl',
    'auth_mode' => 'login'
);

// MONOLOG
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../../app/storage/log/prod.log',
    'monolog.name'    => 'pid-app-prod',
    'monolog.level'   => Logger::WARNING,
));

$app['cache.path'] = __DIR__ . '/../storage/cache';
// CACHES
// Http cache
$app['http_cache.cache_dir'] = $app['cache.path'] . '/http';
// Twig cache
$app['twig.options.cache'] = $app['cache.path'] . '/twig';
