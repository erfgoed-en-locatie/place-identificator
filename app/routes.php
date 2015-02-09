<?php
/**
 *
 * Routes for this Silex app
 *
 */

// homepage
$app->get('/', 'Pid\Demo\Controller\\Home::page');

// simple test route
$app->get('/test/{id}', function ($id) use ($app) {

    return $app['twig']->render('home.html.twig', array('gekozen' => $id));
})
    ->bind('test')
    ->value('id', null) // default
    ->assert('id', '\d+')
;

$app->mount('/datasets', new \Pid\Mapper\Provider\DataSetProvider());

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