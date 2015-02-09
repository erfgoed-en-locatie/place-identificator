<?php

namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\Dataset;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DataSetProvider
 * List datasets (for a certain user)
 *
 * @package Pid\Demo
 */
class DataSetProvider implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/active', array(new self(), 'showActive'))->bind('datasets-active');
        $controllers->get('/upload', array(new self(), 'uploadForm'))->bind('datasets-upload-form');
        $controllers->post('/upload', array(new self(), 'handleUpload'))->bind('datasets-upload');

        $controllers->get('/', array(new self(), 'showAll'))->bind('datasets-all');
        $controllers->post('/', array(new self(), 'handleForm'))->bind('datasets-create');

        // voorbeeldje met optionele parameter
        $controllers->get('/{id}', array(new self(), 'showDataset'))->bind('datasets-show')->value('id', null);
        $controllers->get('/{id}/edit', array(new self(), 'showForm'))->bind('datasets-edit')->value('id', null);
        $controllers->get('/{id}/delete', array(new self(), 'deleteSet'))->bind('datasets-delete')->value('id', null);

        return $controllers;
    }

    /**
     * List all datasets
     *
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function showAll(Application $app)
    {
        /** @var \Doctrine\DBAL\Connection $db */
        $db = $app['db'];

        $stmt = $db->prepare("SELECT * FROM datasets");
        $stmt->execute();
        $datasets = $stmt->fetchAll(
            \PDO::FETCH_CLASS,
            '\Pid\Mapper\Model\Dataset'
        );

        return $app['twig']->render('datasets/list.html.twig', array('datasets' => $datasets));
    }

    /**
     * List only active datasets
     *
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function showActive(Application $app)
    {
        /** @var \Doctrine\DBAL\Connection $db */
        $db = $app['db'];

        $stmt = $db->prepare("SELECT * FROM datasets where status != :status");
        $stmt->execute(array('status' => Dataset::STATUS_FINISHED));
        $datasets = $stmt->fetchAll(
            \PDO::FETCH_CLASS,
            '\Pid\Mapper\Model\Dataset'
        );

        return $app['twig']->render('datasets/list.html.twig', array('datasets' => $datasets));
    }


    /**
     * Show all the details for one dataset
     *
     * @param Application $app
     * @param $id
     */
    public function showDataset(Application $app, $id)
    {
        $stmt = $app['db']->prepare("SELECT * FROM datasets where id = :id");
        $stmt->execute(array('id' => $id));

        $dataset = $stmt->fetch(
            \PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE,
            '\Pid\Mapper\Model\Dataset'
        );

        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }
        return $app['twig']->render('datasets/details.html.twig', array('dataset' => $dataset));
    }

    /**
     * Checks if the user is logged in and is allowed to upload a dataset
     *
     * @param Application $app
     * @return string
     */
    public function uploadForm(Application $app)
    {
        return 'Hier komt het upload formulier ... ALS iemnad is ingelogd en niet al 100 sets heeft geupload? ';
    }

    public function deleteSet(Application $app, $id)
    {
        return 'En hier gaan we dan dataset nummertje ' . $id . ' deleten...';
    }
}