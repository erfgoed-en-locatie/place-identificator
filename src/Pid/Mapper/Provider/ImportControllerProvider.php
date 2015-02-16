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

        $controllers->get('/mapcsv/{id}', array(new self(), 'mapCsv'))->bind('import-mapcsv')->assert('id', '\d+');
        $controllers->post('/mapcsv/{id}', array(new self(), 'handleCsvMapping'))->bind('import-handle-csv')->assert('id', '\d+');

        return $controllers;
    }

    /**
     * Form for csv file uploads
     *
     * @param Application $app
     * @return mixed
     */
    private function getUploadForm(Application $app) {
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
        $form = $this->getUploadForm($app);

        return $app['twig']->render('import/uploadform.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleUpload(Application $app, Request $request)
    {
        $form = $this->getUploadForm($app);
        $form->bind($request);
        if ($form->isValid()) {
            $files = $request->files->get($form->getName());

            $filename = $files['csvFile']->getClientOriginalName();
            $files['csvFile']->move($app['upload_dir'], $filename);

            $data = $form->getData();
            $date = new \DateTime('now');

            // todo hernoem het bestand?
            /** @var \Doctrine\DBAL\Connection $db */
            $db = $app['db'];
            $db->insert('datasets', array(
                'name'      => $data['name'],
                'filename'  => $filename,
                'created_on' => $date->format('Y-m-d H:i:s'),
                'status'    => Dataset::STATUS_NEW,
                'user_id'   => (int) $app['user']->getId()
            ));
            $datasetId = $db->lastInsertId();
            if (!$datasetId) {
                $app['session']->getFlashBag()->set('error', 'Sorry er is iets fout gegaan met opslaan.');
            } else {
                $app['session']->getFlashBag()->set('alert', 'Het bestand is opgeslagen!');
            }

            return $app->redirect($app['url_generator']->generate('import-mapcsv', array('id' => $datasetId)));
        }

        // of toon errors:
        return $app['twig']->render('import/uploadform.html.twig', array(
            'form' => $form->createView()
        ));
    }


    private function getFIeldMapForm(Application $app, $fieldChoices) {
        $form = $app['form.factory']
            ->createBuilder('form')

            ->add('placename', 'choice', array(
                'label'         => 'Welk veld bevat de te standaardiseren plaatsnaam? (verplicht)',
                'choices'   => $fieldChoices,
                'empty_value' => 'selecteer een veld',
                'required'  => true,
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('identifier', 'choice', array(
                'label'         => 'Is er een veld met uw kenmerk of id dat in het eindresultaat terug moet komen?',
                'required'  => false,
                'choices'   => $fieldChoices,
                'empty_value' => 'selecteer een veld',
                'constraints' =>  array(
                    new Assert\NotBlank(),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))

            // todo add a fieldset
            ->add('province', 'text', array(
                'label'         => 'Is er een veld met uw kenmerk of id dat in het eindresultaat terug moet komen?',
                'required'  => false,
                'constraints' =>  array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('country', 'text', array(
                'label'         => 'Is er een veld met uw kenmerk of id dat in het eindresultaat terug moet komen?',
                'required'  => false,
                'constraints' =>  array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->getForm()
        ;
        return $form;
    }

    /**
     * Takes the csv and shows the user the fields that were found so he can map them
     *
     * @param Application $app
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function  mapCsv(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');
            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        // attempt to make sense of the csv file
        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $dataset['filename'];

        $csv = \League\Csv\Reader::createFromPath($file);
        $columnNames = $csv->fetchOne();

        return $app['twig']->render('import/field-mapper.twig', array(
            'columnNames' => $columnNames
        ));
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
        /** @var \Doctrine\DBAL\Connection $db */
        $db = $app['db'];

        $request->get('naam van het veld');
    }

}