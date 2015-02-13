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

        $controllers->get('/test/{datasetId}', array(new self(), 'testAction'))->bind('standardize-test');
        $controllers->get('/run/{datasetId}', array(new self(), 'standardizeAction'))->bind('standardize');

        $controllers->get('/mapcsv', array(new self(), 'mapCsv'))->bind('dataset-mapcsv');
        $controllers->post('/mapcsv', array(new self(), 'handleCsvMapping'))->bind('datasets-create');


        return $controllers;
    }

    /**
     * Fetch the dataset from the db and run the requested mapping against the API for the first x records (NUMBER_TO_TEST)
     *
     * @param Application $app
     * @param integer $datasetId
     * @return string
     */
    public function testAction(Application $app, $datasetId)
    {
        $stmt = $app['db']->prepare("SELECT * FROM datasets where id = :id");
        $stmt->execute(array('id' => $datasetId));

        $dataset = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($datasetId) does not exist.");
        }
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

    /**
     * Takes the csv and shows the user the fields that were found so he can map
     * @param Application $app
     */
    public function  mapCsv(Application $app)
    {
        return $app['twig']->render('import/field-mapper.twig', array('dummy' => 'iets'));
    }

    /**
     * Handles the mapping form and validates it and stores the data in the database
     * Sens user to test or standardize depending on params
     *
     * @param Application $app
     * @param Request $request
     */
    public function handleCsvMapping(Application $app, Request $request)
    {
        $request->get('naam van het veld');
    }

}