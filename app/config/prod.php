<?php

// SITE
$app['sitename'] = 'PiD - Place Identificator';
$app['upload_dir'] = __DIR__ . '/../storage/uploads';

// Locale
$app['locale'] = 'nl';
$app['session.default_locale'] = $app['locale'];

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
            'address' => 'do-not-reply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname()),
            'name' => null,
        ),
    ),

    'emailConfirmation' => array(
        'required' => false, // Whether to require email confirmation before enabling new accounts.
        'template' => '@user/email/confirm-email.twig',
    ),

    'passwordReset' => array(
        'template' => '@user/email/reset-password.twig',
        'tokenTTL' => 86400, // How many seconds the reset token is valid for. Default: 1 day.
    ),

    // Set this to use a custom User class.
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
     // Ensure that the login page is accessible to all, if you set anonymous => false below.
    'login' => array(
        'pattern' => '^/user/login$',
    ),
    'register' => array(
        'pattern' => '^/user/register$',
    ),
    'forgot_password' => array(
        'pattern' => '^/user/forgot-password$',
    ),
    'homepage' => array(
        'pattern' => '^/$',
    ),
    'colofon' => array(
        'pattern' => '^/colofon$',
    ),
    // everything else is secured
    'secured_area' => array(
        'pattern' => '^.*$',
        'anonymous' => false,
        'remember_me' => array(),
        'form' => array(
            'login_path' => '/user/login',
            'check_path' => '/user/login_check',
        ),
        'logout' => array(
            'logout_path' => '/user/logout',
        ),
        'users' => $app->share(function($app) { return $app['user.manager']; }),
    ),
);


// DOCTRINE DBAL
$app["db.options"] = array(
    'driver'   => 'pdo_mysql',
    'host'     => 'localhost',
    'user'     => 'root',
    'password' => 'dreis002',
    'dbname'   => 'pid',
    'charset'   => 'utf8',
);

// MAILER
$app['swiftmailer.options'] = array(
    'host' => 'host',
    'port' => '25',
    'username' => 'username',
    'password' => 'password',
    'encryption' => null,
    'auth_mode' => null
);

$app['cache.path'] = __DIR__ . '/../storage/cache';
// CACHES
// Http cache
$app['http_cache.cache_dir'] = $app['cache.path'] . '/http';
// Twig cache
$app['twig.options.cache'] = $app['cache.path'] . '/twig';