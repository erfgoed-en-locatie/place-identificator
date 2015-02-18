<?php
/**
 * Bootstrap file for this Silex app
 *
 */

// CUSTOM services
$app['dataset_service'] = $app->share(function ($app) {
    return new \Pid\Mapper\Service\DatasetService($app['db']);
});
$app['geocoder_service'] = $app->share(function ($app) {
    return new \Pid\Mapper\Service\GeocoderService();
});

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


// SYMFONY FORM THING
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider());

// CACHE
$app->register(new Silex\Provider\HttpCacheServiceProvider());


// MONOLOG
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__ . '/../app/storage/log/dev.log',
    'monolog.name'    => 'app',
    'monolog.level'   => 300 // = Logger::WARNING
));

return $app;
