<?php

namespace Pid\Mapper\Service;

use Histograph\Client\GeoJsonResponse;
use Histograph\Client\Search;
use Pid\Mapper\Model\Status;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Encapsulates the calls to the Histograph API and stores the requested fields, based on the field mapping provided
 *
 * @package Pid\Mapper\Service
 */
class GeocoderService
{

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Maps an array of rows with at least a placename against the geocoder
     *
     * @param array $rows
     * @param array $fieldMapping
     * @param integer $datasetId
     * @return bool
     */
    public function map($rows, $fieldMapping, $datasetId)
    {
        // todo create batches!
        if (empty($rows)) {
            throw new RuntimeException('No rows to process.');
        }

        $placeColumn = (int)$fieldMapping['placename'];
        if (!$rows[0][$placeColumn]) {
            throw new RuntimeException('Error calling geocoder: no placename column in the rows.');
        }

        $client = new Search($this->app['monolog']);
        //$client->setBaseUri('http://pid.silex/server/serve.php?');

        // client settings valid for all rows
        $client->setGeometry($fieldMapping['geometry'])
            ->setExact(true)
            ->setQuoted(true)
            ->setSearchType($fieldMapping['hg_type']);

        // settings for each row
        foreach ($rows as $row) {
            $originalName = $client->cleanupSearchString($row[(int)($fieldMapping['placename'])]);
            if (empty($originalName)) {
                continue;
            }

            // set bounding param if one was given
            if (!empty($fieldMapping['liesin'])) {
                $within = $client->cleanupSearchString($row[(int)($fieldMapping['liesin'])]);
                if (!empty($within)) {
                    $client->setLiesIn($within);
                }
            }

            /** @var GeoJsonResponse $histographResponse */
            $histographResponse = $client->search($originalName);
            $data = [];

            if ($this->hits = $histographResponse->getHits() > 0) {

                $features = $histographResponse
                    // fetch only results of a certain type:
                    ->setPitSourceFilter(array($fieldMapping['hg_dataset']))
                    ->getFilteredResponse();

                $hits = count($features);

                if ($hits == 1) {
                    /** @var DatasetService $dataService */
                    $dataService = $this->app['dataset_service'];
                    $data = $this->transformPiTs2Rows($originalName, $datasetId, $features, $fieldMapping['hg_dataset']);
                    $dataService->storeGeocodedRecords($data);
                } elseif ($hits > 1) {
                    $data['hits'] =  $hits;
                    $data['status'] = Status::MAPPED_EXACT_MULTIPLE;
                    $data['original_name'] = $originalName;
                    $data['dataset_id'] = $datasetId;
                    $data['hg_dataset'] = $fieldMapping['hg_dataset'];
                    $this->app['dataset_service']->storeMappedRecord($data);
                } else {
                    $data['original_name'] = $originalName;
                    $data['dataset_id'] = $datasetId;
                    $data['hg_dataset'] = $fieldMapping['hg_dataset'];
                    $data['status'] = Status::MAPPED_EXACT_NOT_FOUND;
                    $data['hits'] = 0;
                    $this->app['dataset_service']->storeMappedRecord($data);
                }
            } else {
                $data['original_name'] = $originalName;
                $data['dataset_id'] = $datasetId;
                $data['hg_dataset'] = $fieldMapping['hg_dataset'];
                $data['status'] = Status::MAPPED_EXACT_NOT_FOUND;
                $data['hits'] = 0;
                $this->app['dataset_service']->storeMappedRecord($data);
            }
        }

        return true;
    }

    /**
     * Transform the result from the API into storable data
     *
     * @param string $originalName The search string
     * @param int $datasetId Id of the dataset
     * @param array $features Response GeoJson Features
     * @param string $hgSource The HG source (or dataset) the response was filtered on
     * @return array
     */
    protected function transformPiTs2Rows(
        $originalName,
        $datasetId,
        $features,
        $hgSource,
        $status = Status::MAPPED_EXACT
    ) {
        $data = [];
        // pffht this should also be just 1 record... duh
        foreach ($features as $feature) {

            foreach ($feature->properties->pits as $pit) {
                $row = [];
                $row['hg_id'] = $pit->hgid;
                $row['hg_name'] = $pit->name;
                $row['hg_type'] = $pit->type;
                if (property_exists($pit, 'uri')) {
                    $row['hg_uri'] = $pit->uri;
                }
                if (property_exists($pit, 'geometryIndex') && $pit->geometryIndex > -1) {
                    $row['hg_geometry'] = json_encode($feature->geometry->geometries[$pit->geometryIndex]);
                }

                // hg info
                $row['original_name'] = $originalName;
                $row['dataset_id'] = $datasetId;
                $row['hg_dataset'] = $hgSource;
                $row['status'] = $status;
                $row['hits'] = 1;
                $data[] = $row;
            };
        }

        return $data;
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
            if (empty($json->features)) {
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
            } else {
                if ($this->searchOn == self::SEARCH_MUNICIPALITIES) {
                    $hitCount = 0;
                    // look for only municipalities
                    foreach ($json->features as $feature) {
                        if ($feature->properties->type == self::API_MUNICIPALITY_TYPE) {
                            $hitCount++;
                            $output['data'] = $this->getStandardizedDataForSaving($feature);
                        }
                    }
                    $output['hits'] = $hitCount;
                } else {
                    if ($this->searchOn == self::SEARCH_PLACES_AND_MUNICIPALITIES) {
                        $output['data'] = [];
                        $hitCount = 0;
                        foreach ($json->features as $feature) {
                            // @fixme later: for now we are really only handling places or municipalities!!
                            if ($feature->properties->type == self::API_MUNICIPALITY_TYPE || $feature->properties->type == self::API_PLACE_TYPE) {
                                $hitCount++;
                                $output['data'] = array_merge($output['data'],
                                    $this->getStandardizedDataForSaving($feature));
                            }
                        }
                        $output['hits'] = $hitCount;
                    }
                }
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
        foreach ($feature->properties->pits as $pit) {
            if (in_array($pit->source, $this->fieldsOfInterest)) {

                $data[$pit->source]['name'] = $pit->name;
                if (property_exists($pit, 'uri')) {
                    $data[$pit->source]['uri'] = $pit->uri;
                }
                if ($pit->geometryIndex > -1) {
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
        foreach ($feature->properties->pits as $pit) {
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