<?php
/**
 * Bootstrap file for this Silex app
 *
 */

// CUSTOM services
use Knp\Provider\ConsoleServiceProvider;
use Symfony\Component\HttpFoundation\Request;

$app['csv_service'] = $app->share(function ($app) {
    return new \Pid\Mapper\Service\CsvService($app['upload_dir']);
});
$app['dataset_service'] = $app->share(function ($app) {
    return new \Pid\Mapper\Service\DatasetService($app['db'], $app['csv_service']);
});
$app['histograph_search_client'] = $app->share(function ($app) {
    return new \Histograph\Api\Search([], $app['monolog']);
});
$app['geocoder_service'] = $app->share(function ($app) {
    return new \Pid\Mapper\Service\GeocoderService($app['dataset_service'], $app['histograph_search_client']);
});
$app['uri_resolver_service'] = $app->share(function ($app) {
    return new \Pid\Mapper\Service\UriResolverService();
});

// CONSOLE
$app->register(new ConsoleServiceProvider(), array(
    'console.name' => 'ConsoleApp',
    'console.version' => '1.0.0',
    'console.project_directory' => __DIR__ . '/..'
));


// TWIG
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.options' => array(
        'cache'            => isset($app['twig.options.cache']) ? $app['twig.options.cache'] : false,
        'strict_variables' => true
    ),
    'twig.path'    => array(__DIR__ . '/../app/views')
));

// TWIG extensions
$app["twig"] = $app->share($app->extend("twig", function (\Twig_Environment $twig, Silex\Application $app) {
    $twig->addExtension(new \Pid\Mapper\Twig\StatusFilter($app));
    $twig->addExtension(new \Pid\Mapper\Twig\DatasetStatusFilter($app));
    $twig->addExtension(new \Pid\Mapper\Twig\SearchOptionFilter($app));
    return $twig;
}));


// DOCTRINE DBAL
$app->register(new Silex\Provider\DoctrineServiceProvider());

// SWIFT MAILER
$app->register(new Silex\Provider\SwiftmailerServiceProvider());
$app['swiftmailer.use_spool'] = false;

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


// SIMPLE USER
$app['user.options'] = array(

    // Specify custom view templates here.
    'templates' => array(
        'layout' => 'layout.html.twig',
        'register' => 'simple-user/register.twig',
        'register-confirmation-sent' => 'simple-user/register-confirmation-sent.twig',
        'login' => 'simple-user/login.twig',
        'login-confirmation-needed' => 'simple-user/login-confirmation-needed.twig',
        'forgot-password' => 'simple-user/forgot-password.twig',
        'reset-password' => 'simple-user/reset-password.twig',
        'view' => 'simple-user/view.twig',
        'edit' => 'simple-user/edit.twig',
        'list' => 'simple-user/list.twig',
        //'list' => '@user/list.twig',
    ),

    // Configure the user mailer for sending password reset and email confirmation messages.
    'mailer' => array(
        'enabled' => true, // When false, email notifications are not sent (they're silently discarded).
        'fromEmail' => array(
            //'address' => 'do-not-reply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname()),
            'address' => 'histograph.io@gmail.com',
            'name' => 'LocatieNaarUri website',
        ),
    ),

    'emailConfirmation' => array(
        'required' => false, // Whether to require email confirmation before enabling new accounts.
        'template' => 'simple-user/email/confirm-email.twig',
    ),

    'passwordReset' => array(
        'template' => 'simple-user/email/reset-password.twig',
        'tokenTTL' => 86400, // How many seconds the reset token is valid for. Default: 1 day.
    ),

    'userClass' => 'SimpleUser\User',

    // Whether to require that users have a username (default: false).
    // By default, users sign in with their email address instead.
    'isUsernameRequired' => false,

    // A list of custom fields to support in the edit controller.
    'editCustomFields' => array(),

    // Override table names, if necessary.
    'userTableName' => 'users',
    'userCustomFieldsTableName' => 'user_custom_fields',
);

// Security config, for SIMPLE USER See http://silex.sensiolabs.org/doc/providers/security.html for details.
$app['security.firewalls'] = array(
    'secure' => array(
        'anonymous' => true,
        'pattern' => '^/.*$',
        'form' => array('login_path' => '/user/login', 'check_path' => '/user/login_check'),
        'logout' => array('logout_path' => '/user/logout'),
        'users' => $app->share(function ($app) { return $app['user.manager']; }),
    ),
);


$app['security.access_rules'] = array(
    array('^/user/list/.*$', 'ROLE_ADMIN'),
    array('^/import/.*$', 'ROLE_USER'),
    array('^/datasets/.*$', 'ROLE_USER'),
    array('^/api/.*$', 'ROLE_USER'),
    array('^/standardize/.*$', 'ROLE_USER'),
    array('^/', 'IS_AUTHENTICATED_ANONYMOUSLY'),
);

return $app;
