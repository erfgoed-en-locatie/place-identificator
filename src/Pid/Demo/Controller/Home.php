<?php

namespace Pid\Demo\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Home
 * Simple demo controller
 *
 */
class Home
{

    public function page(Request $request, Application $app)
    {
        return $app['twig']->render('home.html.twig', array());
    }

}