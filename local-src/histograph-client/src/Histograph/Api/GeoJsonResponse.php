<?php

namespace Histograph\Api;


use Histograph\Exception\BadResponseException;
use Histograph\PitTypes;
use Histograph\Sources;

/**
 * Returns a usable response from the Histograph API
 */
class GeoJsonResponse
{
    /** @var  integer */
    protected $hits;

    /** @var \StdClass Json as an object */
    protected $json;

    /** @var  array Filters to apply on the json response */
    protected $filters = array(
        'source' => null,
        'type'   => null
    );

    public function __construct($json)
    {
        $this->json = $json;
        $this->handleResponse($json);

        return $this;
    }

    /**
     * Checks if we have a proper response
     *
     * @param $json
     * @return array The array contains hits|data keys
     */
    private function handleResponse($json)
    {
        if (!property_exists($json, 'features')) {
            throw new BadResponseException('No features in the responce from the API');
        } else {
            if (empty($json->features)) {
                $this->hits = 0;
            } else {
                $this->hits = count($json->features);
                $this->tempFeatures = $json->features;
            }
        }
    }

    /**
     * Returns the straightforward json response as it was retrieved from the server
     *
     * @return string
     */
    public function getJsonResponse()
    {
        return $this->json;
    }

    /**
     * Returns the features
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->json->features;
    }

    /**
     * Filters the response on one or more sources
     *
     * @param array $sources
     * @return $this
     */
    public function setPitSourceFilter(array $sources)
    {
        foreach ($sources as $source) {
            if (!in_array($source, Sources::getAllSets())) {
                throw new \RuntimeException("Unknown histograph source '{$source}'.");
            }
        }

        $this->filters['source'] = $sources;

        return $this;
    }

    /**
     * Filters the response on one or more PiT types
     * Use this when you want to be able to search for all, but are only interested in the results of certain PiT types
     *
     * @param array $types
     * @return $this
     */
    public function setPitTypeFilter(array $types)
    {
        foreach ($types as $type) {
            if (!in_array($type, PitTypes::getTypes())) {
                throw new \RuntimeException("Unknown histograph type '{$type}'.");
            }
        }
        $this->filters['type'] = $types;

        return $this;
    }

    /**
     * Filter the response on one or more specific PiT source(s)
     * For instance: only PiTs that have a "nwb" source
     *
     *
     * @return mixed|null|object
     */
    public function getFilteredResponse()
    {
        $tempCollection = $this->json->features;

        if ($this->hits > 0) {
            foreach ($tempCollection as $key => $feature) {
                $filteredPits = [];
                if (property_exists($feature->properties, 'pits')) {
                    foreach ($feature->properties->pits as $pit) {
                        // filter on both conditions
                        if ($this->filters['source'] && $this->filters['type']) {
                            if (in_array($pit->dataset, $this->filters['source']) &&
                                in_array($pit->type, $this->filters['type'])
                            ) {
                                $filteredPits[] = $pit;
                            }
                        }
                        // or just one
                        elseif ($this->filters['source'] && !$this->filters['type']) {
                            if (in_array($pit->dataset, $this->filters['source'])) {
                                $filteredPits[] = $pit;
                            }
                        }
                        // or the other
                        elseif (!$this->filters['source'] && $this->filters['type']) {
                            if (in_array($pit->type, $this->filters['type'])) {
                                $filteredPits[] = $pit;
                            }
                        }
                    }
                    $feature->properties->pits = $filteredPits;
                }
            }

            // clean up if there were any features found that have no pits with the required type or source
            foreach ($tempCollection as $key => $col) {
                if (count($col->properties->pits) === 0) {
                    unset ($tempCollection[$key]);
                }
            }

            return $tempCollection;
        }

        return null;
    }

    /**
     * @return int
     */
    public function getHits()
    {
        return $this->hits;
    }
}
