<?php

namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\Dataset;
use Pid\Mapper\Model\Status;
use Pid\Mapper\Service\GeocoderService;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use SimpleUser\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Straightforward controller to provide a way to perform some simple storage actions through ajax calls
 *
 * @package Pid\Mapper\Provider
 */
class ApiControllerProvider implements ControllerProviderInterface {

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/record/unmap/{id}', array(new self(), 'clearStandardization'))->bind('api-clear-mapping')->assert('id', '\d+');
        $controllers->post('/record/map/{id}', array(new self(), 'setStandardization'))->bind('api-set-mapping')->assert('id', '\d+');


        return $controllers;
    }

    /**
     * Delete the found standardized info for a certain record and reset it to an UNMAPPED status
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function clearStandardization(Application $app, $id)
    {
        if ($app['dataset_service']->clearRecord($id)){
            return $app->json(array('id' => $id));
        }

        return $app->json(array('error' => 'Record could not be updated'), 400);
    }

    /**
     * Standardize record with UNMAPPED status
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function setStandardization(Application $app, $id)
    {   
        // http://www.islandsofmeaning.nl/projects/resolve_uri/?uri=http://www.geonames.org/2759797
        
        return $app->json(array('id' => $id));
        if ($app['dataset_service']->clearRecord($id)){
            return $app->json(array('message' => "update geslaagd"));
        }

        return $app->json(array('error' => 'Record could not be updated'), 400);
    }

}