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
        $controllers->get('/record/map/{id}', array(new self(), 'setStandardization'))->bind('api-set-mapping')->assert('id', '\d+');
        $controllers->get('/record/ummappable/{id}', array(new self(), 'setUnmappable'))->bind('api-unmappable')->assert('id', '\d+');


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

        return $app->json(array('error' => 'Record could not be updated'), 503);
    }


    /**
     * Delete the found standardized info for a certain record and reset it to an UNMAPPED status
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function setUnmappable(Application $app, $id)
    {
        if ($app['dataset_service']->setRecordAsUnmappable($id)){
            return $app->json(array('id' => $id));
        }

        return $app->json(array('error' => 'Record could not be updated'), 503);
    }

    /**
     * Standardize record with UNMAPPED status
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function setStandardization(Application $app, Request $request, $id)
    {
        //$data = $request->getContent();
        $uri = $request->get('uri');
        // todo find out how to do this with a proper POST as json data
        if (!is_string($uri)) {
            return $app->json(array('error' => 'Invalid uri received'), 400);
        }
        try {
            $record = $app['uri_resolver_service']->findOne($uri);

            $column = $this->discoverSourceType($uri);
            $data[$column] = $record;
            if ($app['dataset_service']->storeManualMapping($data, $id)){
                return $app->json(array('id' => $id));
            }

        } catch (\Exception $e) {
            return $app->json(array('id' => $id), 503);
        }

    }

    /**
     * Find out what sort of uri it is (geonames/tgn etc)
     *
     * @param $uri
     * @return string The name matches the column name of table.
     */
    protected function discoverSourceType($uri)
    {
        if (strpos($uri, 'geonames')) {
            return 'geonames';
        } else if (strpos($uri, 'getty')) {
            return 'tgn';
        } else if (strpos($uri, 'gemeentegeschiedenis')) {
            return 'gg';
        } else if (strpos($uri, 'kadaster')) {
            return 'bag';
        } else {
            return 'erfgeo';
        }
    }

}