<?php

namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\Dataset;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use SimpleUser\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds all the controllers for automatically calling the geocoder API
 *
 * @package Pid\Mapper\Provider
 */
class StandardizeControllerProvider implements ControllerProviderInterface {


    const NUMBER_TO_TEST    = 20;

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/test/{id}', array(new self(), 'testAction'))->bind('standardize-test')->assert('id', '\d+');
        $controllers->get('/run/{id}', array(new self(), 'standardizeAction'))->bind('standardize')->assert('id', '\d+');



        return $controllers;
    }

    /**
     * Fetch the dataset from the db and run the requested mapping against the API for the first x records (NUMBER_TO_TEST)
     *
     * @param Application $app
     * @param integer $id
     * @return string
     */
    public function testAction(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');
            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        // attempt to make sense of the csv file
        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $dataset['filename'];
        $csv = \League\Csv\Reader::createFromPath($file);

        // todo, iets met eerste rij buiten beschouwing laten
        $rows = $csv->setOffset(0)->setLimit(self::NUMBER_TO_TEST)->fetchAll();

        var_dump($rows); die;
        return $app['twig']->render('standardize/test-result.twig', array('dataset' => $dataset));
    }

    /**
     * Attempt to map the entire file
     * Run all the calls to the API and send the user an email when done
     *
     * @param Application $app
     * @param integer $datasetId
     */
    public function standardizeAction(Application $app, $datasetId)
    {
        $app->redirect('dataset-upload-form');
    }


}