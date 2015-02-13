<?php


namespace Pid\Mapper\Provider;

use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use SimpleUser\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ImportControllerProvider
 *
 * @package Pid\Mapper\Provider
 */
class ImportControllerProvider implements ControllerProviderInterface {

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/upload', array(new self(), 'uploadForm'))->bind('dataset-upload-form');
        $controllers->post('/upload', array(new self(), 'handleUpload'))->bind('dataset-upload');

        $controllers->get('/mapcsv', array(new self(), 'mapCsv'))->bind('dataset-mapcsv');
        $controllers->post('/mapcsv', array(new self(), 'handleCsvMapping'))->bind('datasets-create');


        return $controllers;
    }

    /**
     * Checks if the user is logged in and is allowed to upload a dataset
     *
     * @param Application $app
     * @return string
     */
    public function uploadForm(Application $app)
    {
        //return 'Hier komt het upload formulier ... ALS iemnad is ingelogd en niet al 100 sets heeft geupload? ';
        return $app['twig']->render('import/uploadform.html.twig', array());
    }

    /**
     *
     * @param Application $app
     * @param Request $request
     */
    public function handleUpload(Application $app, Request $request)
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