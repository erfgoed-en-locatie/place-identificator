<?php

namespace Pid\Mapper\Service;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Maps a set of rows and returns the result
 *
 * @package Pid\Mapper\Service
 */
class GeocoderService {

    const SEARCH_MUNICIPALITIES = 99;
    const SEARCH_PLACES         = 98;
    const SEARCH_BOTH           = 97;

    /**
     * @var string $baseUri Uri of the service to call
     */
    //private $baseUri = 'http://erfgeo.nl/histograph/';
    private $baseUri = 'http://pid.silex/answer.json?';

    /**
     * @var integer Whether to search the geocoder for places or municipalities or both
     */
    private $searchOn = self::SEARCH_PLACES;

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client();
    }

    /**
     * Maps an array of rows with at least a placename against the geocoder
     *
     * @param array $rows
     * @param integer $key Index key of the row that holds the placename
     * @return mixed
     */
    public function map($rows, $key)
    {
        if (!$rows[0][$key]) {
            throw new RuntimeException('Error calling geocoder: no placename column in the rows.');
        }

        foreach($rows as &$row) {
            $name = $row[$key];

            // try and catch ofzo
            $response = $this->client->get($this->searchExact($name));
            if ($response->getStatusCode() === 200) {
                $row['reponse'] = $this->handleResponse($response->json());
            }

        }
    }

    /**
     * Provides the uri for exact searching
     *
     * @param $name
     * @return string
     */
    private function searchExact($name) {
        return $this->baseUri . 'search?name=' . $name;
    }

    /**
     * Loops through the clumps and tries to find PITs
     * @param $json
     */
    private function handleResponse($json)
    {
        if ($this->searchOn == self::SEARCH_PLACES) {

            if ($json)

            var_dump(count($json['features']));

        }

    }

}