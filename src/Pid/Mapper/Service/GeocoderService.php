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

    /** @var DatasetService */
    protected $datasetService;

    /** @var Search */
    protected $searchClient;

    public function __construct(DatasetService $datasetService, Search $searchClient)
    {
        $this->datasetService = $datasetService;
        $this->searchClient = $searchClient;
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
        // client settings valid for all rows
        $this->searchClient->setGeometry(true)
            ->setExact(true)
            ->setQuoted(true)
            ->setSearchType($fieldMapping['hg_type']);
        $originalName = $this->searchClient->cleanupSearchString($row['original_name']);
        if (empty($originalName)) {
            //print 'No name to search on ' . $row['original_name'];
            return false;
        }

        // todo set liesIn param if one was given
        // can't do this now since we don not have teh liesIn field copied in here .. will be possible once I fix all of that
        /*if (!empty($fieldMapping['liesin'])) {
            $within = $this->searchClient->cleanupSearchString($row[(int)($fieldMapping['liesin'])]);
            $this->searchClient->setLiesIn($within);
        }*/

        /** @var GeoJsonResponse $histographResponse */
        $histographResponse = $this->searchClient->search($originalName);
        if (!$histographResponse) {
            return false;
        }

        if ($histographResponse->getHits() > 0) {

            $features = $histographResponse
                // fetch only results of a certain type:
                ->setPitSourceFilter(array($fieldMapping['hg_dataset']))
                ->getFilteredResponse();

            return $features;
         }

        return null;
    }

    /**
     * Test a few rows against the Geocoder
     *
     * @param $rows
     * @param $dataset
     * @return bool
     */
    public function mapTest($rows, $dataset)
    {
        if (empty($rows)) {
            throw new RuntimeException('No rows to process.');
        }

        // client settings valid for all rows
        $this->searchClient->setGeometry(false)
            ->setExact(true)
            ->setQuoted(true)
            ->setSearchType($dataset['hg_type']);

        $output = [];

        // settings for each row
        foreach ($rows as $row) {
            $originalName = $this->searchClient->cleanupSearchString($row[(int)($dataset['placename_column'])]);
            if (empty($originalName)) {
                continue;
            }

            // set bounding param if one was given
            $within = null;
            if (!empty($dataset['liesin_column'])) {
                $within = $this->searchClient->cleanupSearchString($row[(int)($dataset['liesin_column'])]);
                $this->searchClient->setLiesIn($within);
            }

            /** @var GeoJsonResponse $histographResponse */
            $histographResponse = $this->searchClient->search($originalName);
            if (!$histographResponse) {
                return false;
            }
            $data = [];

            if ($this->hits = $histographResponse->getHits() > 0) {
                $features = $histographResponse
                    // fetch only results of a certain type:
                    ->setPitSourceFilter(array($dataset['hg_dataset']))
                    ->getFilteredResponse();
                $hits = count($features);

                if ($hits == 1) {
                    $data = $this->transformPiTs2Rows($originalName, $dataset, $features, $within);
                    $output[] = $data[0];
                } elseif ($hits > 1) {
                    // eigenlijk meerdere records opslaan?
                    $data = $this->transformPiTs2Rows($originalName, $dataset, $features, $within, Status::getFormattedOption(Status::MAPPED_EXACT_MULTIPLE));
                    foreach ($data as $foundPit) {
                        $output[] = $foundPit;
                    }
                }

            } else {
                $data['original_name'] = $originalName;
                $data['liesin_name'] = $within;
                $data['dataset_id'] = $dataset['id'];
                $data['hg_dataset'] = $dataset['hg_dataset'];
                $data['status'] = Status::getFormattedOption(Status::MAPPED_EXACT_NOT_FOUND);
                $data['hits'] = 0;
                $output[] = $data;
            }
        }

        return $output;
    }


    /**
     * Maps an array of rows with at least a placename against the geocoder
     * Also stores the result in the database
     *
     * @param array $rows
     * @param $dataset
     * @return bool
     */
    public function map($rows, $dataset)
    {
        if (empty($rows)) {
            throw new RuntimeException('No rows to process.');
        }

        // client settings valid for all rows
        $this->searchClient->setGeometry($dataset['geometry'])
            ->setExact(true)
            ->setQuoted(true)
            ->setSearchType($dataset['hg_type']);

        // settings for each row
        foreach ($rows as $row) {
            $originalName = $this->searchClient->cleanupSearchString($row['original_name']);
            if (empty($originalName)) {
                continue;
            }

            // set bounding param if one was given
            $within = null;
            if (strlen($dataset['liesin_column']) > 0) {
                $within = $this->searchClient->cleanupSearchString($row['liesin_name']);
                $this->searchClient->setLiesIn($within);
            }

            /** @var GeoJsonResponse $histographResponse */
            $histographResponse = $this->searchClient->search($originalName);
            $data = [];

            if (!$histographResponse) {
                return false;
            }

            if ($this->hits = $histographResponse->getHits() > 0) {

                $features = $histographResponse
                    // fetch only results of a certain type:
                    ->setPitSourceFilter(array($dataset['hg_dataset']))
                    ->getFilteredResponse();

                $hits = count($features);

                if ($hits == 1) {
                    $data = $this->transformPiTs2Rows($originalName, $dataset, $features, $within);

                    $this->datasetService->storeGeocodedRecords($data);
                } elseif ($hits > 1) {
                    $data['hits'] =  $hits;
                    $data['status'] = Status::MAPPED_EXACT_MULTIPLE;
                    $data['original_name'] = $originalName;
                    $data['liesin_name'] = $within;
                    $data['dataset_id'] = $dataset['id'];
                    $data['hg_dataset'] = $dataset['hg_dataset'];
                    $this->datasetService->updateRecord($data);
                } else {
                    $data['original_name'] = $originalName;
                    $data['liesin_name'] = $within;
                    $data['dataset_id'] = $dataset['id'];
                    $data['hg_dataset'] = $dataset['hg_dataset'];
                    $data['status'] = Status::MAPPED_EXACT_NOT_FOUND;
                    $data['hits'] = 0;
                    $this->datasetService->updateRecord($data);
                }
            } else {
                $data['original_name'] = $originalName;
                $data['liesin_name'] = $within;
                $data['dataset_id'] = $dataset['id'];
                $data['hg_dataset'] = $dataset['hg_dataset'];
                $data['status'] = Status::MAPPED_EXACT_NOT_FOUND;
                $data['hits'] = 0;
                $this->datasetService->updateRecord($data);
            }
        }

        return true;
    }

    /**
     * Transform the result from the API into storable data
     *
     * @param string $originalName The search string
     * @param int $dataset
     * @param array $features Response GeoJson Features
     * @param $within
     * @param int $status
     * @return array
     * @internal param string $hgSource The HG source (or dataset) the response was filtered on
     */
    protected function transformPiTs2Rows(
        $originalName,
        $dataset,
        $features,
        $within,
        $status = Status::MAPPED_EXACT
    ) {
        $data = [];
        $hits = count($features);
        // pffht this should also be just 1 record... duh
        foreach ($features as $feature) {

            foreach ($feature->properties->pits as $pit) {
                $row = [];

                // hg info
                $row['original_name'] = $originalName;
                $row['liesin_name'] = $within;
                $row['dataset_id'] = $dataset['id'];
                $row['hg_dataset'] = $dataset['hg_dataset'];
                $row['status'] = $status;
                $row['hits'] = $hits;
                $row['hg_id'] = '';
                if (property_exists($pit, 'id')) {
                    $row['hg_id'] = $pit->id;
                }
                $row['hg_name'] = $pit->name;
                $row['hg_type'] = $pit->type;
                $row['hg_uri'] = '';
                if (property_exists($pit, 'uri')) {
                    $row['hg_uri'] = $pit->uri;
                }
                $row['hg_geometry'] = '';
                if (property_exists($pit, 'geometryIndex') && $pit->geometryIndex > -1) {
                    $row['hg_geometry'] = json_encode($feature->geometry->geometries[$pit->geometryIndex]);
                }

                $data[] = $row;
            };
        }

        return $data;
    }

}