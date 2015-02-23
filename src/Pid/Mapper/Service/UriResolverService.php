<?php

namespace Pid\Mapper\Service;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Resolves the uri's as they were found in Histograph, using the resolver of Islands of Meaning
 *
 * @package Pid\Mapper\Service
 */
class UriResolverService {


    /**
     * @var string $baseUri Uri of the service to call
     */
    private $baseUri = 'http://www.islandsofmeaning.nl/projects/resolve_uri/';

    /**
     * @var array Fields in the API result that hold the data we want to store
     */
    private $fieldsOfInterest = array(
        'label', 'lon', 'lat'
    );

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client();
    }


    /**
     * Call Menno's uri resolver for a given uri
     *
     * @param string $uri
     * @return array The array contains hits|data keys
     */
    public function findOne($uri)
    {
        $apiUri = $this->baseUri . '?uri=' . $uri;
        $response = $this->client->get($apiUri);
        if ($response->getStatusCode() === 200) {
            return $this->handleResponse($response->json(array('object' => true)));

        }
    }

    public function handleResponse($json)
    {
        $data['name'] = $json->label[0];
        $data['uri'] = $json->uri;
        $data['geomety']['type'] = 'Point';
        $data['geometry']['coordinates'] = array($json->lat, $json->lon);
        return $data;
    }


}