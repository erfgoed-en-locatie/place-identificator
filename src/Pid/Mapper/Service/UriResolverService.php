<?php

namespace Pid\Mapper\Service;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Resolves the uri's as they were found in Histograph
 *
 * @package Pid\Mapper\Service
 */
class UriResolverService {


    /**
     * @var string $baseUri Uri of the service to call
     */
    private $baseUri = 'http://erfgeo.nl/histograph/';

    /**
     * @var array Fields in the API result that hold the data we want to store
     */
    private $fieldsOfInterest = array(
        'geonames', 'tgn', 'bag', 'gemeentegeschiedenis'
    );

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client();
    }


    /**
     * Call API for one place name and try to find as much info as possible
     *
     * @param string $name
     * @return array The array contains hits|data keys
     */
    public function findOne($name)
    {
        $response = $this->client->get($this->searchExact($name));
        if ($response->getStatusCode() === 200) {
            return $this->handleMapOneResponse($response->json(array('object' => true)));
        }
    }


}