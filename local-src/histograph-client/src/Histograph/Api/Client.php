<?php

namespace Histograph\Api;

use Monolog\Logger;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Base class for the Histograph API client that does common calls to the API
 */
class Client extends GuzzleClient
{

    const API_TIMEOUT = 10;
    const API_CONNECT_TIMEOUT = 10;

    protected $baseUri = 'https://api.histograph.io';
    //protected $baseUri = 'http://histograph-lb-2072119452.eu-central-1.elb.amazonaws.com';

    /**
     * @var \Monolog\Logger
     */
    protected $logger = null;

    /**
     * Initializes the guzzle client with the passed configuration options for this client.
     * Simply pass in the base_url if you want to override the default client url
     *
     * With optional injected Logger
     *
     * @param array $config COnfig settings for Guzzleclient
     * @param Logger $logger
     */
    public function __construct($config = array(), Logger $logger = null)
    {
        $settings =[
            'defaults' => [
                'timeout' => self::API_TIMEOUT,
                'connect_timeout' => self::API_CONNECT_TIMEOUT,
                'allow_redirects' => false
            ]
        ];

        if (is_array($config)) {
            $settings = array_merge($settings, $config);
        }

        $this->logger = $logger;

        parent::__construct($settings);

        $this->setDefaultOption('headers/Accept-Charset', 'utf-8');
        $this->setDefaultOption('headers/Accept', 'application/json');
        $this->setDefaultOption('headers/Content-Type', 'application/json');

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
        $response = $this->get($this->baseUri);

        if ($response->getStatusCode() === 200) {
            if ('Histograph API' === $response->json()['name']) {
                return true;
            }
        }

        return false;
    }

    /**
     * List the available datasets in Histograph
     *
     * @param bool|false $idOnly Whether to return only the id value
     * @return bool
     */
    public function listDatasets($idOnly = false)
    {
        $response = $this->get('/datasets');

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
     * @return \Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

}
