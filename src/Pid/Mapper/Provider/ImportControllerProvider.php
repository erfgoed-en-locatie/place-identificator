<?php


namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\Dataset;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use SimpleUser\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

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

        $controllers->get('/mapcsv', array(new self(), 'mapCsv'))->bind('import-mapcsv');
        $controllers->post('/mapcsv', array(new self(), 'handleCsvMapping'))->bind('import-handle-csv');

        return $controllers;
    }

    /**
     * Form for csv file uploads
     *
     * @param Application $app
     * @return mixed
     */
    private function getForm(Application $app) {
        $form = $app['form.factory']
            ->createBuilder('form')

            ->add('name', 'text', array(
                'label'         => 'Geef uw dataset een herkenbare naam',
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern'     => '/^[a-z0-9-\s]+$/i',
                        'htmlPattern' => '^[a-z0-9-\s]+$',
                        'match'   => true,
                        'message' => 'Voer alleen letters of cijfers in',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('csvFile', 'file', array(
                'label'     => 'Kies een csv-bestand op uw computer',
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\File(array(
                        'maxSize'       => '4096k',
                        'mimeTypes'     => array('text/csv', 'text/plain'),
                    )),
                    new Assert\Type('file')
                )
            ))
            ->getForm()
        ;
        return $form;
    }
    /**
     * Checks if the user is logged in and is allowed to upload a dataset
     *
     * @param Application $app
     * @return string
     */
    public function uploadForm(Application $app)
    {
        $form = $this->getForm($app);

        return $app['twig']->render('import/uploadform.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     *
     * @param Application $app
     * @param Request $request
     */
    public function handleUpload(Application $app, Request $request)
    {
        $form = $this->getForm($app);
        $form->bind($request);
        if ($form->isValid()) {
            $files = $request->files->get($form->getName());

            $filename = $files['csvFile']->getClientOriginalName();
            $files['csvFile']->move($app['upload_dir'], $filename);

            $data = $form->getData();
            $date = new \DateTime('now');

            $app['db']->insert('datasets', array(
                'name'      => $data['name'],
                'filename'  => $filename,
                'created_on' => $date->format('Y-m-d H:i:s'),
                'status'    => Dataset::STATUS_NEW,
                'user_id'   => (int) $app['user']->getId()
            ));
            $app['session']->getFlashBag()->set('alert', 'Het bestand is opgeslagen!');

            return $app->redirect($app['url_generator']->generate('import-mapcsv'));
        }
        // of toon errors:
        return $app['twig']->render('import/uploadform.html.twig', array(
            'form' => $form->createView()
        ));
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