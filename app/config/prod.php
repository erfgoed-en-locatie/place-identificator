<?php

// SITE
$app['sitename'] = 'PiD - Place Identificator';

// Locale
$app['locale'] = 'en';
$app['session.default_locale'] = $app['locale'];

// DOCTRINE DBAL
$app["db.options"] = array(
    'driver'   => 'pdo_mysql',
    'host'     => 'localhost',
    'user'     => 'root',
    'password' => 'dreis002',
    'dbname'   => 'pid',
    'charset'   => 'utf8',
);

/*// MYSQLi
$app['mysqli.configuration'] = array(
    'host'     => 'localhost',
    'username' => 'root',
    'password' => 'dreis002',
    'database' => 'pid',
    'charset'  => 'utf8',
);*/

// MAILER
$app['swiftmailer.options'] = array(
    'host' => 'host',
    'port' => '25',
    'username' => 'username',
    'password' => 'password',
    'encryption' => null,
    'auth_mode' => null
);


// CACHES
$app['cache.path'] = __DIR__ . '/../storage/cache';
// Http cache
$app['http_cache.cache_dir'] = $app['cache.path'] . '/http';
// Twig cache
$app['twig.options.cache'] = $app['cache.path'] . '/twig';