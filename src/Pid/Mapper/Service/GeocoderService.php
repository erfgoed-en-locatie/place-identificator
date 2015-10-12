<?php

namespace Pid\Mapper\Service;

use Histograph\Api\GeoJsonResponse;
use Histograph\Api\Search;
use Pid\Mapper\Model\Status;
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
     * Try to find a HG Concept for which we got multiple answers
     *
     * @param $row
     * @param $fieldMapping
     * @return bool
     */
    public function fetchFeaturesForNamesWithMultipleHits($row, $fieldMapping)
    {
        // new Histograph Search Client, should be injected some time soon!
        /** @var Search $client */
        $client = new Search([], $this->app['monolog']);

        // client settings valid for all rows
        $client->setGeometry(true)
            ->setExact(true)
            ->setQuoted(true)
            ->setSearchType($fieldMapping['hg_type']);
        $originalName = $client->cleanupSearchString($row['original_name']);
        if (empty($originalName)) {
            //print 'No name to search on ' . $row['original_name'];
            return false;
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
        // FAKE SERVER!
        //$histographResponse = $client->callApi($originalName, 'http://pid.silex/leiden.json');

        if (!$histographResponse) {
            //print 'No response';
            return false;
        }

        if ($histographResponse->getHits() > 0) {

            $features = $histographResponse
                // fetch only results of a certain type:
                ->setPitSourceFilter(array($fieldMapping['hg_dataset']))
                ->getFilteredResponse();

            return $features;
            //return $this->handleMapOneResponse($features);
         }

        return null;
    }

    /**
     * Maps an array of rows with at least aone placename against the geocoder
     *
     * @param array $rows
     * @param array $fieldMapping
     * @param integer $datasetId
     * @return bool
     */
    public function map($rows, $fieldMapping, $datasetId)
    {
        if (empty($rows)) {
            throw new RuntimeException('No rows to process.');
        }

        $placeColumn = (int)$fieldMapping['placename'];
        if (!$rows[0][$placeColumn]) {
            throw new RuntimeException('Error calling geocoder: no placename column in the rows.');
        }

        // new Histograph Search Client, should be injected some time soon!
        /** @var Search $client */
        $client = new Search([], $this->app['monolog']);

        // client settings valid for all rows
        $client->setGeometry($fieldMapping['geometry']) // todo if we don't store the geometry... don't fetch it
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

            if (!$histographResponse) {
                return false;
            }

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

}