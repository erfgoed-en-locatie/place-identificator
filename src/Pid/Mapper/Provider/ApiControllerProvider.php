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

        $controllers->get('/testuri', array(new self(), 'testResolver'))->bind('api-test');

        $controllers->get('/record/unmap/{id}', array(new self(), 'clearStandardization'))->bind('api-clear-mapping')->assert('id', '\d+');
        $controllers->get('/record/map/{id}', array(new self(), 'setStandardization'))->bind('api-set-mapping')->assert('id', '\d+');
        $controllers->get('/record/ummappable/{id}', array(new self(), 'setUnmappable'))->bind('api-unmappable')->assert('id', '\d+');

        $controllers->post('/record/choose-pit/{id}', array(new self(), 'choosePit'))->bind('api-choose-pit')->assert('id', '\d+');
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
        if ($ids = $app['dataset_service']->setRecordAsUnmappable($id)){
            return $app->json($ids);
        }

        return $app->json(array('error' => 'Record could not be updated'), 503);
    }

    /**
     * Save a manually set mapping
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
        if (empty($uri) || !is_string($uri)) {
            return $app->json(array('error' => 'Geen of geen valide uri ontvangen. Er is niets opgeslagen.'), 400);
        }

        // only gg, geonames and tgn uri's!
        if(!preg_match("/http:\/\/www.gemeentegeschiedenis.nl\/gemeentenaam/", $uri) &&
            !preg_match("/http:\/\/www.geonames.org/", $uri) &&
            !preg_match("/http:\/\/vocab.getty.edu\/tgn/", $uri)) {
            return $app->json(array('error' => 'Geen GG, TGN of GeoNames Uri. Er is niets opgeslagen.'), 400);
        }

        // if geonames, we do'nt want the last part they keep communicating!
        if(preg_match("/(http:\/\/www.geonames.org\/[0-9]+)(\/.*)/", $uri, $matches)){
            //print_r($matches);
            $uri = $matches[1];
        }

        try {
            $record = $app['uri_resolver_service']->findOne($uri);
            $column = $this->discoverSourceType($uri);
            $data[$column] = $record;
            if ($ids = $app['dataset_service']->storeManualMapping($data, $id)){
                return $app->json($ids);
            }
        } catch (\RuntimeException$e) {
            return $app->json(array('id' => $id), 404);
        } catch (\Exception $e) {
            return $app->json(array('id' => $id), 503);
        }
    }

    public function testResolver(Application $app, Request $request)
    {
        $uri = $request->get('uri');

        // only gg, geonames and tgn uri's!
        if(!preg_match("/http:\/\/www.gemeentegeschiedenis.nl\/gemeentenaam/", $uri) &&
            !preg_match("/http:\/\/www.geonames.org/", $uri) &&
            !preg_match("/http:\/\/vocab.getty.edu\/tgn/", $uri)) {
            return $app->json(array('error' => 'Geen GG, TGN of GeoNames Uri. Er is niets opgeslagen.'), 400);
        }

        // if geonames, we do'nt want the last part they keep communicating!
        if(preg_match("/(http:\/\/www.geonames.org\/[0-9]+)(\/.*)/", $uri, $matches)){
            //print_r($matches);
            $uri = $matches[1];
        }

        $record = $app['uri_resolver_service']->findOne($uri);
        var_dump($record); die;

    }


    /**
     * When selecting one PIT of multiple results we need to store the entire klont that get's passed in as json
     *
     * @param Application $app
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function choosePit(Application $app, Request $request, $id)
    {
        $jsonData = json_decode($data = $request->getContent());
        $data = [];

        if (isset($jsonData->geonames)) {
            $data['geonames'] = json_encode($jsonData->geonames);
        }
        if (isset($jsonData->tgn)) {
            $data['tgn'] = json_encode($jsonData->tgn);
        }
        if (isset($jsonData->bag)) {
            $data['bag'] = json_encode($jsonData->bag);
        }
        if (isset($jsonData->gemeentegeschiedenis)) {
            $data['gg'] = json_encode($jsonData->gemeentegeschiedenis);
        }

        if ($app['dataset_service']->storeManualMapping($data, $id)){
            return $app->json(array('id' => $id));
        }

        return $app->json(array('id' => $id), 503);
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
