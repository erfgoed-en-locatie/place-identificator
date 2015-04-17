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

    const API_TIMEOUT           = 5;
    const API_CONNECT_TIMEOUT   = 5;

    const SEARCH_ALL                        = 99;
    const SEARCH_PLACES                     = 98;
    const SEARCH_MUNICIPALITIES             = 97;
    const SEARCH_PLACES_AND_MUNICIPALITIES  = 96;
    const SEARCH_STREETS                    = 90;

    /** fuzzy or eaxcat options */
    const TYPE_LITERAL_EXACT    = 1;
    const TYPE_LITERAL_PART_OF  = 2;
    const TYPE_TOKENIZED        = 3;

    /** @var string The field that the API uses to determine the type of feature */
    const API_PLACE_TYPE        = 'hg:Place';
    const API_MUNICIPALITY_TYPE = 'hg:Municipality';
    const API_STREET_TYPE       = 'hg:Street';

    /** @var array  */
    private $cache = null;

    /** @var array SearchType options for the geocoder */
    public static $searchOptions = array(
        //self::SEARCH_ALL    => 'alles',
        self::SEARCH_PLACES_AND_MUNICIPALITIES => 'plaatsen en gemeentes',
        self::SEARCH_PLACES         => 'plaatsen',
        self::SEARCH_MUNICIPALITIES => 'gemeentes',
        //self::SEARCH_STREETS => 'straten'
    );

    /**
     * @var integer Whether to search the geocoder for a specific hg:Type or not
     */
    private $searchOn = self::SEARCH_PLACES_AND_MUNICIPALITIES;


    private $searchFuzzy = 3;

    /**
     * @var string $baseUri Uri of the service to call
     */
    private $baseUri = 'http://api.histograph.io';

    protected $app;

    /**
     * @var array Fields in the API result that hold the data we want to store
     */
    private $fieldsOfInterest = array(
        'geonames', 'tgn', 'bag', 'gemeentegeschiedenis'
    );

    public function __construct($app)
    {
        $this->app = $app;
        $this->client = new \GuzzleHttp\Client();
    }

    /**
     * Escape characters before they're send to the API
     * @param $name
     * @return string
     */
    protected function filterBadCharacters($name)
    {
        $bad = ':/?#[]@!$&()*+,;='; // @fixme escape some
        return preg_replace('!\s+!', ' ', str_ireplace(str_split($bad), '', $name));
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
     * Call the API but check cache first
     *
     * @param $name
     * @return mixed
     */
    private function callAPI($name)
    {
        if (isset($this->cache[$name])) {
            $this->app['monolog']->addInfo('Fetched from cache: "' . $name .'"');
            return $this->cache[$name];
        }

        $name = $this->filterBadCharacters($name);
        $uri = $this->search($name);
        $this->app['monolog']->addInfo('Calling histograph API with: "' . $uri .'"');

        $response = $this->client->get(
            $uri,
            array(
                'timeout' => self::API_TIMEOUT, // Response timeout
                'connect_timeout' => self::API_CONNECT_TIMEOUT, // Connection timeout
            ));

        $this->cache[$name] = $response;
        return $response;
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
     * Wrapper around setting all the settable parameters for calling the API
     *
     * @param string $name
     * @return string
     */
    protected function search($name)
    {
        $searchOnType = '';
        if ($this->searchOn == self::SEARCH_PLACES) {
            $searchOnType = '&type=' . self::API_PLACE_TYPE;
        }
        if ($this->searchOn == self::SEARCH_MUNICIPALITIES) {
            $searchOnType = '&type=' . self::API_MUNICIPALITY_TYPE;
        }
        if ($this->searchOn == self::SEARCH_STREETS) {
            $searchOnType = '&type=' . self::API_STREET_TYPE;
        }
        // todo create wild card searches and non literal string searches
        // todo make the fuzzy_search options settable and select between searchExact, searchExactPhrase etc
        $uri = $this->searchExact($name) . $searchOnType;

        return $uri;
    }

    /**
     * Searches the API on a literal (quoted) string, and returns only exact matches (not part of)
     * Example: http://api.histograph.io/search?name="Bergen op Zoom"&exact=true
     *
     * @param $name
     * @return string
     */
    private function searchExact($name) {
        return $this->baseUri . '/search?name="' . $name . '"&exact=true';
    }

    /**
     * Searches the API on a literal string, and returns also matches that were partially found
     * (Bergen op zoomstraat)
     * Example: http://api.histograph.io/search?name="Bergen op Zoom"&exact=false
     *
     * @param $name
     * @return string
     */
    private function searchExactPhrase($name) {
        return $this->baseUri . '/search?name="' . $name . '"&exact=false';
    }

    /**
     * Searches the API on a (tokenized) word that is contained by the placename
     *
     * @param $name
     * @return string
     */
    private function searchExactWord($name) {
        return $this->baseUri . '/search?name=' . $name . '&exact=true';
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
                $data[$pit->source]['uri'] = $pit->uri;
                $data[$pit->source]['geometry'] = $feature->geometry->geometries[$pit->geometryIndex];
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

    /**
     * Set the type of search you want to perform
     * @return int
     */
    public function getSearchOn()
    {
        return $this->searchOn;
    }

    /**
     * Set the type of search you want to perform
     *
     * @param int $searchOn
     */
    public function setSearchOn($searchOn)
    {
        if (array_key_exists($searchOn, self::$searchOptions)) {
            $this->searchOn = $searchOn;
        } else {
            $this->searchOn = self::SEARCH_PLACES_AND_MUNICIPALITIES;
        }
    }

}