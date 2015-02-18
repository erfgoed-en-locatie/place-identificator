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

    /** @var string The field that the API uses to determine the type of feature */
    const API_PLACE_TYPE        = 'hg:Place';
    const API_MUNICIPALITY_TYPE = 'hg:Municipality';

    /**
     * @var integer Whether to search the geocoder for places or municipalities or both
     */
    private $searchOn = self::SEARCH_PLACES;

    /**
     * @var string $baseUri Uri of the service to call
     */
    //private $baseUri = 'http://erfgeo.nl/histograph/';
    private $baseUri = 'http://pid.silex/answer.json?';

    /**
     * @var array Fields in the API result that hold the data we want to store
     */
    private $fieldsOfInterest = array(
        'geonames', 'tgn', 'bag', 'gg'
    );

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

            // todo try and catch? or more robust error handling
            $response = $this->client->get($this->searchExact($name));
            if ($response->getStatusCode() === 200) {
                $row['reponse'] = $this->handleResponse($response->json(array('object' => true)));
            }
        }

        return $rows;
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
     * @return array
     */
    private function handleResponse($json)
    {
        if (!property_exists($json, 'features')) {
            return array('hits' => 0);
        } else {
            $output = array();
            if ($this->searchOn == self::SEARCH_PLACES) {

                $hitCount = 0;
                // look for only place types in the features
                foreach ($json->features as $feature) {
                    if ($feature->properties->type == self::API_PLACE_TYPE) {
                        $hitCount++;
                        $output['data'] = $this->getStandardizedData($feature);
                    }
                }
                if ($hitCount == 1) {
                    $output['hits'] = 1;
                } else if ($hitCount > 1) {
                    $output['hits'] = $hitCount;
                }
            } else if ($this->searchOn == self::SEARCH_MUNICIPALITIES) {
                $hitCount = 0;
                // look for only municipalities
                foreach ($json->features as $feature) {
                    if ($feature->properties->type == self::API_MUNICIPALITY_TYPE) {
                        $hitCount++;
                        $output['data'] = $this->getStandardizedData($feature);
                    }
                }
                if ($hitCount == 1) {
                    $output['hits'] = 1;
                } else if ($hitCount > 1) {
                    $output['hits'] = $hitCount;
                }

            } else if ($this->searchOn == self::SEARCH_BOTH) {
                $output['hits'] = count($json->features);

                // only return data if there's only one match
                if ($output['hits'] == 1) {
                    $output['data'] = $this->getStandardizedData($json->features[0]);
                }
            }
            return $output;
        }


    }

    /**
     * Plucks the data we want to store from the geocoder PITs
     *
     * @param object $feature
     * @return array
     */
    private function getStandardizedData($feature)
    {
        $data = array();
        foreach($feature->properties->pits as $pit) {
            if (in_array($pit->source, $this->fieldsOfInterest)) {
                $data[$pit->source]['name'] = $pit->name;
                $data[$pit->source]['uri'] = $pit->uri;
                $data[$pit->source]['geometry'] = $feature->geometry->geometries[$pit->geometryIndex];
            }
        }
        return $data;
    }

}