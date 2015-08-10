<?php

namespace Pid\Mapper\Service;
use Histograph\Client\GeoJsonResponse;
use Histograph\Client\Search;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Encapsulates the calls to the Histograph API and stores the requested fields, based on the field mapping provided
 *
 * @package Pid\Mapper\Service
 */
class GeocoderService {

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
        $this->client = new Search($app['monolog']);
    }

    /**
     * Maps an array of rows with at least a placename against the geocoder
     *
     * @param array $rows
     * @param array $fieldMapping
     */
    public function map($rows, $fieldMapping)
    {
        // todo create batches!
        if (empty($rows)) {
            throw new RuntimeException('No rows to process.');
        }

        $placeColumn = (int) $fieldMapping['placename'];
        if (!$rows[0][$placeColumn]) {
            throw new RuntimeException('Error calling geocoder: no placename column in the rows.');
        }

        // client settings valid for all rows
        $this->client->setGeometry($fieldMapping['geometry'])
            ->setExact(true)
            ->setQuoted(true)
            ->setSearchType($fieldMapping['hg_type']);

        // settings for each row
        foreach ($rows as $row) {
            $name = $this->client->cleanupSearchString($row[(int)($fieldMapping['placename'])]);
            if (empty($name)) {
                continue;
            }

            // set bounding param if one was given
            if (!empty($fieldMapping['liesin'])) {
                $within = $this->client->cleanupSearchString($row[(int)($fieldMapping['liesin'])]);
                if (!empty($within)) {
                    $this->client->setLiesIn($within);
                }
            }

            /** @var GeoJsonResponse $histographResponse */
            $histographResponse = $this->client->search($name);

            if ($this->hits = $histographResponse->getHits() > 0) {;

                $features = $histographResponse
                    // fetch only results of a certain type:
                    ->setPitSourceFilter(array($fieldMapping['hg_dataset']))
                    ->getFilteredResponse()
                    //->getResponse()
                ;

                if ($features) {
                    foreach ($features as $feature) {

                        foreach ($feature->properties->pits as $pit) {

                            print '++ ' . $pit->name . ' -+- ' . $pit->hgid . '<br/>';
                        };
                    }

                }
            }
        }
    }

    protected function storeMappedRow($datasetId, $originalName, $data)
    {

    }

    /**
     * Maps an array of rows with at least a placename against the geocoder
     *
     * @param array $rows
     * @param integer $key Index key of the row that holds the placename
     * @return mixed
     */
    public function oldmap($rows, $key)
    {
        if (!$rows[0][$key]) {
            throw new RuntimeException('Error calling geocoder: no placename column in the rows.');
        }

        if (empty($rows)) {
            throw new RuntimeException('No rows to process.');
        }

        foreach($rows as &$row) {
            $name = $row[$key];

            try {
                $response = $this->callAPI($name);
                if ($response->getStatusCode() === 200) {
                    $row['response'] = $this->handleResponse($response->json(array('object' => true)));
                }
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $this->app['monolog']->addError('Histograph API did not return a response within ' . self::API_TIMEOUT . ' seconds');
                continue;
            }

        }
        // empty the cache
        $this->cache = null;
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
        $response = $this->callAPI($name);
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
                // @fixme later: for now we are really only handling places or municipalities!!
                if ($feature->properties->type == self::API_MUNICIPALITY_TYPE || $feature->properties->type == self::API_PLACE_TYPE) {
                    $hitCount++;
                    $klont = $this->getStandardizedDataForDisplaying($feature);
                    if (count($klont)) {
                        $output['data'][] = $klont;
                    }
                }
            }
            $output['hits'] = $hitCount;

            return $output;
        }

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
            if (empty($json->features)){
                return array('hits' => 0);
            }

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
                $output['hits'] = $hitCount;
            } else if ($this->searchOn == self::SEARCH_MUNICIPALITIES) {
                $hitCount = 0;
                // look for only municipalities
                foreach ($json->features as $feature) {
                    if ($feature->properties->type == self::API_MUNICIPALITY_TYPE) {
                        $hitCount++;
                        $output['data'] = $this->getStandardizedDataForSaving($feature);
                    }
                }
                $output['hits'] = $hitCount;
            } else if ($this->searchOn == self::SEARCH_PLACES_AND_MUNICIPALITIES) {
                $output['data'] = [];
                $hitCount = 0;
                foreach ($json->features as $feature) {
                    // @fixme later: for now we are really only handling places or municipalities!!
                    if ($feature->properties->type == self::API_MUNICIPALITY_TYPE || $feature->properties->type == self::API_PLACE_TYPE) {
                        $hitCount++;
                        $output['data'] = array_merge($output['data'], $this->getStandardizedDataForSaving($feature));
                    }
                }
                $output['hits'] = $hitCount;
            }
            //var_dump($output); die;
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
                if (property_exists($pit, 'uri')) {
                    $data[$pit->source]['uri'] = $pit->uri;
                }
                if($pit->geometryIndex > -1){
                    $data[$pit->source]['geometry'] = $feature->geometry->geometries[$pit->geometryIndex];
                }
                $data[$pit->source]['type'] = $feature->properties->type;
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
                if (isset($feature->geometry->geometries[$pit->geometryIndex])) {
                    $data[$pit->source]['geometry'] = $feature->geometry->geometries[$pit->geometryIndex];
                }

            }
        }
        return $data;
    }

}