<?php

namespace Histograph\Client;

use Histograph\Exception\RuntimeException;
use Monolog\Logger;

/**
 * Base class for the Histograph API client that does common calls to the API
 */
class Client
{

    const API_TIMEOUT           = 5;
    const API_CONNECT_TIMEOUT   = 5;

    /**
     * @var string $baseUri Uri of the service to call
     */
    protected $baseUri = 'http://api.erfgeo.nl';

    /** @var array  */
    protected $cache = null;

    /**
     * @var \Monolog\Logger
     */
    protected $logger = null;

    /**
     * Constructor with optional injected Logger
     *
     * @param Logger $logger
     */
    public function __construct(Logger $logger = null)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->logger = $logger;
    }

    /**
     * Clean up the search string
     * @param $name
     * @return string
     */
    public function cleanupSearchString($name)
    {
        return trim($name, ". \t\n\r");
    }

    /**
     * Check if the API is up at all?
     *
     * @return mixed
     */
    public function isUp()
    {
        if ($this->logger) {
            $this->logger->addDebug('checking if the thing is up at all');
        }
        $response = $this->client->get(
            $this->baseUri,
            array(
                'timeout' => self::API_TIMEOUT,
                'connect_timeout' => self::API_CONNECT_TIMEOUT,
            )
        );

        if ($response->getStatusCode() === 200) {
            if ('Histograph API' === $response->json()['name']) {
                return true;
            }
        }

            return false;
    }

    /**
     * List the available sources in Histograph
     *
     * @param bool|false $idOnly Whether to return only the id value
     * @return bool
     */
    public function listSources($idOnly = false)
    {
        $response = $this->client->get(
            $this->baseUri . '/sources',
            array(
                'timeout' => self::API_TIMEOUT,
                'connect_timeout' => self::API_CONNECT_TIMEOUT,
            )
        );

        if ($response->getStatusCode() === 200) {
            if (true === $idOnly) {
                $sources = [];
                foreach ($response->json() as $source) {
                    $sources[] = $source['id'];
                }
                return $sources;
                }

                return $response->json();
            }

            return false;
    }

    /**
     * @return string
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }

    /**
     * @param string $baseUri
     */
    public function setBaseUri($baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * @return \Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param \Monolog\Logger $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
}
