<?php
/**
 *
 * Routes for this Silex app
 *
 */

// homepage
$app->get('/', 'Pid\Demo\Controller\\Home::page');

// simple route
$app->get('/colofon', function () use ($app) {
    return $app['twig']->render('colofon.html.twig');
});

$app['histograph_api'] = $app['histograph_search_client']->getBaseUri();

// a complete set of routes
$app->mount('/datasets', new \Pid\Mapper\Provider\DataSetControllerProvider());
$app->mount('/import', new \Pid\Mapper\Provider\ImportControllerProvider());
$app->mount('/standardize', new \Pid\Mapper\Provider\StandardizeControllerProvider());
$app->mount('/api', new \Pid\Mapper\Provider\ApiControllerProvider());
$app->mount('/file', new \Pid\Mapper\Provider\FileControllerProvider());

// Mount the user controller routes:
$app->mount('/user', $simpleUserProvider);

// Error route
$app->error(function (\Exception $e, $code) use ($app) {
    switch ($code) {
        case 404:
            $message = $app['twig']->render('404.html.twig');
            break;
        default:
            $message = 'We are sorry, but something went terribly wrong.';
    }

    if ($app['debug']) {
        $message .= ' Error Message: ' . $e->getMessage();
    }

    return new Symfony\Component\HttpFoundation\Response($message, $code);
});

return $app;