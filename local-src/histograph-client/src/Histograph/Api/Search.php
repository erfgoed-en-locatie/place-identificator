<?php

namespace Histograph\Api;

use Histograph\PitTypes;

/**
 * Search client for the Histpgraph API that uses the /search endpoint
 */
class Search extends Client
{
    /** The api endpoint */
    const ENDPOINT = '/search';

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
    protected $fuzzy = 0;

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
     * Searches the API by uri
     *
     * @param string $uri
     * @return bool|GeoJsonResponse
     */
    public function findByUri($uri)
    {
        $searchUri = self::ENDPOINT . '?uri=' . urlencode($uri);

        return $this->callApi($uri, $searchUri);
    }

    /**
     * Searches the API by id
     *
     * @param string $id
     * @return bool|GeoJsonResponse
     */
    public function findById($id)
    {
        $searchUri = self::ENDPOINT . '?id=' . urlencode($id);

        return $this->callApi($id, $searchUri);
    }

    /**
     * Call the API to perform the search but checks the cache first
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

        return $this->callApi($name, $uri);
    }

    /**
     * Compose the search Uri
     *
     * @param string $name
     * @return string
     */
    public function composeSearchUri($name)
    {
        $searchUri = $this->baseUri . self::ENDPOINT . '?q=';

        // name first
        if (true === $this->isQuoted()) {
            $name = '"' . $name . '"';
        }
        $searchUri .= $name;

        if ($this->liesIn && !empty($this->filterBadCharacters($this->liesIn))) {
            $searchUri .= ', ' . $this->filterBadCharacters($this->liesIn);
            //$searchUri .= '&related=hg:liesIn&related.q=' . $this->filterBadCharacters($this->liesIn);
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
            throw new \InvalidARgumentException("Unrecognized search type ({$searchType}). We can't search for that!");
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

    /**
     * Actually calls the API
     *
     * @param $name
     * @param $uri
     * @return bool|GeoJsonResponse
     */
    public function callApi($name, $uri)
    {
        if ($this->logger) {
            $this->logger->addDebug('Calling histograph API with: "' . $uri . '"');
        }

        try {
            $response = $this->get($uri);
            if ($response->getStatusCode() === 200) {
                $geoJson = $response->json(array('object' => true));
                $this->cache[$name] = $geoJson;

                return new GeoJsonResponse($geoJson);
            } else {
                if ($this->logger) {
                    $this->logger->addError('Histograph API reported ' . $response->getReasonPhrase());
                }
                throw new \RuntimeException('Histograph API could not be searched.');
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($this->logger) {
                $this->logger->addError('Histograph API returned with the following error: ' . $e->getMessage());
            }
        }

        return false;
    }
}
