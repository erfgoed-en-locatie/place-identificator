<?php
/**
 * Bootstrap file for this Silex app
 *
 */

// TWIG
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.options' => array(
        'cache'            => isset($app['twig.options.cache']) ? $app['twig.options.cache'] : false,
        'strict_variables' => true
    ),
    'twig.path'    => array(__DIR__ . '/../app/views')
));

// DOCTRINE DBAL
$app->register(new Silex\Provider\DoctrineServiceProvider());

// SWIFT MAILER
$app->register(new Silex\Provider\SwiftmailerServiceProvider());

// needed for SimpleUser
$app->register(new Silex\Provider\SecurityServiceProvider());
$app->register(new Silex\Provider\RememberMeServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
// SimpleUser
$simpleUserProvider = new SimpleUser\UserServiceProvider();
$app->register($simpleUserProvider);

// CACHE
$app->register(new Silex\Provider\HttpCacheServiceProvider());


// MONOLOG
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../app/storage/log/dev.log',
    'monolog.name'    => 'app',
    'monolog.level'   => 300 // = Logger::WARNING
));

return $app;
