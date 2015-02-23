<?php

namespace Pid\Mapper\Service;
use Symfony\Component\Config\Definition\Exception\Exception;
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

            $response = $this->client->get($this->searchExact($name));
            if ($response->getStatusCode() === 200) {
                $row['response'] = $this->handleResponse($response->json(array('object' => true)));
            }
        }

        return $rows;
    }

    /**
     * Call API for one place name and try to find as much info as possible
     *
     * @param string $name
     * @return array The array contains hits|data keys
     */
    public function mapOne($name)
    {
        $response = $this->client->get($this->searchExact($name));
        if ($response->getStatusCode() === 200) {
            return $this->handleMapOneResponse($response->json(array('object' => true)));
        }
    }

    /**
     * Loops through the clumps and tries to find PITs
     *
     * @param $json
     * @return array The array contains hits|data keys
     */
    private function handleMapOneResponse($json)
    {
        if (!property_exists($json, 'features')) {
            return array('hits' => 0);
        } else {
            $output = array();

            $hitCount = 0;
            foreach ($json->features as $feature) {
                $hitCount++;
                $output['data'][] = $this->getStandardizedDataForDisplaying($feature);
            }
            $output['hits'] = $hitCount;

            return $output;
        }

    }

    /**
     * Provides the uri for exact searching
     *
     * @param $name
     * @return string
     */
    private function searchExact($name) {
        $fakeAPI = array('answer.json', 'no-answer.json', 'answer-middelburg.json');
        $fakeAPI = array('answer-middelburg.json');
        return 'http://pid.silex/' . array_rand(array_flip($fakeAPI), 1);
        //return $this->baseUri . 'search?name=' . $name;
    }

    /**
     * Loops through the clumps and tries to find PITs
     *
     * @param $json
     * @return array The array contains hits|data keys
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
                        $output['data'] = $this->getStandardizedDataForSaving($feature);
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
                        $output['data'] = $this->getStandardizedDataForSaving($feature);
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
                    $output['data'] = $this->getStandardizedDataForSaving($json->features[0]);
                }
            }
            return $output;
        }

    }

    /**
     * Plucks the data to show the user (so he can make a selection)
     *
     * @param object $feature
     * @return array
     */
    private function getStandardizedDataForDisplaying($feature)
    {
        $data = array();
        foreach($feature->properties->pits as $pit) {
            if (in_array($pit->source, $this->fieldsOfInterest)) {
                $data[$pit->source]['name'] = $pit->name;
                $data[$pit->source]['uri'] = $pit->uri;
                $data[$pit->source]['geometry'] = $feature->geometry->geometries[$pit->geometryIndex];
                $data[$pit->source]['type'] = $pit->type;
            }
        }
        return $data;
    }

    /**
     * Plucks the data we want to store from the geocoder PITs
     *
     * @param object $feature
     * @return array
     */
    private function getStandardizedDataForSaving($feature)
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