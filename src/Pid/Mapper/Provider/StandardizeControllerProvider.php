<?php

namespace Pid\Mapper\Provider;

use Histograph\Client\Client;
use Histograph\Client\GeoJsonResponse;
use Histograph\Client\Search;
use Pid\Mapper\Model\Dataset;
use Pid\Mapper\Model\Status;
use Pid\Mapper\Service\CsvService;
use Pid\Mapper\Service\DatasetService;
use Pid\Mapper\Service\GeocoderService;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use SimpleUser\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Actions for automatically calling the geocoder API
 *
 */
class StandardizeControllerProvider implements ControllerProviderInterface
{

    const NUMBER_TO_TEST = 20;

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/test/{id}', array(new self(), 'testAction'))->bind('standardize-test')->assert('id', '\d+');
        $controllers->get('/run/{id}', array(new self(), 'standardizeAction'))->bind('standardize')->assert('id',
            '\d+');

        return $controllers;
    }

    /**
     * Fetch the dataset from the db and run the requested mapping against the API for the first x records (NUMBER_TO_TEST)
     *
     * @param Application $app
     * @param integer $id
     * @return string
     */
    public function testAction(Application $app, $id, Request $request)
    {
        /** @var DatasetService $dataService */
        $dataService = $app['dataset_service'];

        $dataset = $dataService->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        // new test action: fetch first x records and save them to a csv file... and display as html
        /** @var CsvService $csvService */
        $csvService = $app['csv_service'];
        $csvRows = $csvService->getRows($dataset, self::NUMBER_TO_TEST);

        if (strlen($dataset['placename_column']) < 1) {
            $app['session']->getFlashBag()->set('error',
                'Sorry maar voor de dataset zijn de standaardisatie opties nog niet ingevuld. Dat moet eerst gedaan worden.');

            return $app->redirect($app['url_generator']->generate('import-mapcsv', array('id' => $id)));
        }

        /** @var GeocoderService $geocoder */
        $geocoder = $app['geocoder_service'];

        try {
            $output = $geocoder->mapTest($csvRows, $dataset);
            $csvService->writeTestFile($dataset, $output);
        } catch (\Exception $e) {
            $app['monolog']->error($e->getMessage());
            $app['session']->getFlashBag()->set('error',
                'Sorry, maar er is iets mis met de Histograph API. Probeer het svp wat later nog eens.');
        }

        return $app->redirect($app['url_generator']->generate('dataset-test-result', array('id' => $id)));
    }

    /**
     * Attempt to map the entire file
     * Run all the calls to the API and send the user an email when done
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function standardizeAction(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        exec('php ../bin/pid standardize ' . $id . ' > /dev/null &');
        $app['session']->getFlashBag()->set('alert',
            'De standaardisatie is begonnen! U krijgt een mail als het proces klaar is.');

        return $app->redirect($app['url_generator']->generate('datasets-all'));
    }

}