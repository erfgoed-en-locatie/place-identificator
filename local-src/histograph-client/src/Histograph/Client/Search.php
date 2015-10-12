<?php

namespace Histograph\Client;

use Histograph\Exception\RuntimeException;
use Histograph\PitTypes;

/**
 * Search client for the Histpgraph API that uses the /search endpoint
 */
class Search extends Client
{
    /**
     * Whether to search for a specific hg:Type or not
     * @var string
     */
    protected $searchType = null;

    /**
     * Use a quoted search term or not
     * @var boolean
     */
    protected $quoted = false;

    /**
     * Whether to search exact or not.
     *
     * @var boolean
     */
    protected $exact = false;

    /**
     * Set the fuzzy factor, when available
     * @var float
     */
    protected $fuzzy;

    /** @var  string Set a bounding parameter for the liesIn relation */
    protected $liesIn = null;

    /** @var bool Whether to fetch the result with or without geometries */
    protected $geometry = true;

    /**
     * Escape characters before they're send to the API
     * @param $name
     * @return string
     */
    protected function filterBadCharacters($name)
    {
        $bad = ':/?#[]@!$&()*+;='; // @fixme escape some
        return preg_replace('!\s+!', ' ', str_ireplace(str_split($bad), '', $name));
    }

    /**
     * Call the API to perform the search but check cache first
     *
     * @param $name
     * @return mixed
     */
    public function search($name)
    {
        if (isset($this->cache[$name])) {
            if ($this->logger) {
                $this->logger->addDebug('Fetched from cache: "' . $name . '"');
            }
            return new GeoJsonResponse($this->cache[$name]);
        }

        $name = $this->filterBadCharacters($name);
        $uri = $this->composeSearchUri($name);
        if ($this->logger) {
            $this->logger->addDebug('Calling histograph API with: "' . $uri . '"');
        }
        try {
            $response = $this->client->get(
                $uri,
                array(
                    'timeout' => self::API_TIMEOUT, // Response timeout
                    'connect_timeout' => self::API_CONNECT_TIMEOUT, // Connection timeout
                )
            );

            if ($response->getStatusCode() === 200) {
                $geoJson = $response->json(array('object' => true));
                    //$geoJson = $response->json();
                    $this->cache[$name] = $geoJson;
                    return new GeoJsonResponse($geoJson);
            } else {
                if ($this->logger) {
                    $this->logger->addError('Histograph API reported ' . $response->getReasonPhrase());
                }
                throw new RuntimeException('Histograph API could not be searched');
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($this->logger) {
                $this->logger->addError('Histograph API did not return a response within ' . self::API_TIMEOUT . ' seconds');
            }
        }
        return false;
    }

    /**
     * Call the API and perform the search
     *
     * @param string $name
     * @return string
     */
    protected function composeSearchUri($name)
    {
        $searchUri = $this->baseUri . '/search?q=';

        // name first
        if (true === $this->isQuoted()) {
            $name = '"' . $name . '"';
        }
        $searchUri .= $name;

        if ($this->liesIn) {
            $searchUri .= ', ' . $this->filterBadCharacters($this->liesIn);
        }

        // specific type
        if ($this->searchType) {
            $searchUri .= '&type=' . $this->searchType;
        }

        if (true === $this->isExact()) {
            $searchUri .= '&exact=true';
        } else {
            $searchUri .= '&exact=false';
        }

        if ($this->getFuzzy()) {
            // todo implement fuzzy with q search
        }

        if (false === $this->isGeometry()) {
            $searchUri .= '&geometry=false';
        }

        //print $searchUri . PHP_EOL;
        return $searchUri;
    }

    /**
     * Searches the API on a literal (quoted) string, and returns only exact matches (not part of)
     * Example: http://api.histograph.io/search?name="Bergen op Zoom"&exact=true
     *
     * Searches the API on a literal string, and returns also matches that were partially found
     * (Bergen op zoomstraat)
     * Example: http://api.histograph.io/search?name="Bergen op Zoom"&exact=false
     *
     * Searches the API on a (tokenized) word that is contained by the placename
     * Example: http://erfgeo.nl/thesaurus/#search=bergen%20op%20zoom%20&exact=true
     *

    /**
     * @return boolean
     */
    public function isGeometry()
    {
        return $this->geometry;
    }

    /**
     * @param boolean $geometry
     * @return $this
     */
    public function setGeometry($geometry)
    {
        $this->geometry = $geometry;

        return $this;
    }

    /**
     * @return string
     */
    public function getSearchType()
    {
        return $this->searchType;
    }

    /**
     * @param string $searchType
     * @return $this
     */
    public function setSearchType($searchType)
    {
        if (!in_array($searchType, PitTypes::getTypes())) {
            throw new RuntimeException("Unrecognized search type ({$searchType}). We can't search for that!");
        }
        $this->searchType = $searchType;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isQuoted()
    {
        return $this->quoted;
    }

    /**
     * @param boolean $quoted
     * @return $this
     */
    public function setQuoted($quoted)
    {
        $this->quoted = $quoted;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isExact()
    {
        return $this->exact;
    }

    /**
     * @param boolean $exact
     * @return $this
     */
    public function setExact($exact)
    {
        $this->exact = $exact;

        return $this;
    }

    /**
     * @return float
     */
    public function getFuzzy()
    {
        return $this->fuzzy;
    }

    /**
     * @param float $fuzzy
     * @return $this
     */
    public function setFuzzy($fuzzy)
    {
        $this->fuzzy = $fuzzy;

        return $this;
    }

    /**
     * @return string
     */
    public function getLiesIn()
    {
        return $this->liesIn;
    }

    /**
     * @param string $liesIn
     * @return $this
     */
    public function setLiesIn($liesIn)
    {
        $this->liesIn = $liesIn;

        return $this;
    }
}
