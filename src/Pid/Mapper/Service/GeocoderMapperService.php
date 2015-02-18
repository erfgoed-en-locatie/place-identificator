<?php

namespace Pid\Mapper\Service;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Maps a set of rows and returns the result
 *
 * @package Pid\Mapper\Service
 */
class GeocoderService {

    /**
     * @var string $baseUri Uri of the service to call
     */
    //private $baseUri = 'http://erfgeo.nl/histograph/search';
    private $baseUri = 'http://pid.silex/answer.json';

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
        foreach($rows as $row) {
            $name = $row[$key];

            $response = $this->client->get($this->searchExact($name));
            if ($response->getStatusCode() === 200) {

                //
                return $response->json();
            }
        }
    }

    private function searchExact($name) {
        return $this->baseUri . 'search?name=' . $name;
    }

    private function handleResponse($response)
    {

    }

}