<?php

require __DIR__  . '../../../../vendor/autoload.php';

use Histograph\Client\GeoJsonResponse;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


// init Monolog
$logger = new Logger('histograph-client');
$logger->pushHandler(new StreamHandler(__DIR__ . '/hoorn.log', Logger::DEBUG));

$name = 'HOORN';

$client = new Histograph\Client\Search($logger);

/** @var GeoJsonResponse $histographResponse */
$histographResponse =
    $client
        ->setGeometry(false)
        ->setExact(true)
        ->setQuoted(true)
        //->setLiesIn('Leiden')
        ->setSearchType('hg:Place')
        ->search($name)
;

// get only nwb PiTs
if ($total = $histographResponse->getHits() > 0) {
    print 'Found: '. $total . PHP_EOL;
    $features = $histographResponse
        ->setPitSourceFilter(array('geonames'))
        ->getFilteredResponse();

    if ($features) {
        foreach ($features as $feature) {

            foreach ($feature->properties->pits as $pit) {

                print '++ ' .$pit->name . ' -+- ' . $pit->hgid . PHP_EOL;
            };
        }

    }
}