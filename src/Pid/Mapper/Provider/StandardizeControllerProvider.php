<?php

namespace Pid\Mapper\Provider;

use Histograph\Client\Client;
use Histograph\Client\GeoJsonResponse;
use Histograph\Client\Search;
use Pid\Mapper\Model\Dataset;
use Pid\Mapper\Model\Status;
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
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        // attempt to make sense of the csv file
        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $dataset['filename'];
        if (!file_exists($file)) {
            $app['session']->getFlashBag()->set('error', 'Sorry maar het csv-bestand bestaat niet meer.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }
        $csv = \League\Csv\Reader::createFromPath($file);
        $csv->setDelimiter(current($csv->detectDelimiterList(2)));

        $limit = self::NUMBER_TO_TEST;
        if ($dataset['skip_first_row']) {
            $limit++;
        }
        $csvRows = $csv->setOffset(0)->setLimit($limit)->fetchAll();
        if ($dataset['skip_first_row']) {
            array_shift($csvRows);
        }

        $fieldMapping = $app['dataset_service']->getFieldMappingForDataset($id);
        if (!$fieldMapping) {
            $app['session']->getFlashBag()->set('error',
                'Sorry maar er zijn nog geen instellingen voor dat csv-bestand.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        /** @var GeocoderService $geocoder */
        $geocoder = $app['geocoder_service'];

        try {
            /** @var DatasetService $dataService */
            $dataService = $app['dataset_service'];
            // todo petra implement deleteOld or different status/match types 'exact" etc

            $dataService->clearRecordsForDataset($id);
            $geocoder->map($csvRows, $fieldMapping, $id);

        } catch (\Exception $e) {
            $app['monolog']->error($e->getMessage());
            $app['session']->getFlashBag()->set('error',
                'Sorry, maar er is iets mis met de Histograph API. Probeer het svp wat later nog eens.');
            //$app->abort(404, 'The histograph API returned an error. It might be down.');
        }

        return $app->redirect($app['url_generator']->generate('datasets-show', array('id' => $id)));
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