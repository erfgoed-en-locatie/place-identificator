<?php


namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\Dataset;
use Pid\Mapper\Service\DatasetService;
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

        $controllers->match('/mapcsv/{id}', array(new self(), 'mapCsv'))->bind('import-mapcsv')->assert('id', '\d+')->method('GET|POST');

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
            ->add('skip_first_row', 'choice', array(
                'label'     => 'Bevat de eerste rij de kolomnamen?',
                'required'  => true,
                'choices'   => array(1 => 'Ja', 0 => 'Nee'),
                'constraints' =>  array(
                    new Assert\Type('integer')
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
                'skip_first_row' => $data['skip_first_row'],
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

    /**
     * Create the form form the field mapping
     *
     * @param Application $app
     * @param $fieldChoices
     * @return mixed
     */
    private function getFieldMapForm(Application $app, $fieldChoices) {
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

            ->add('province', 'choice', array(
                'label'         => 'Provincie',
                'required'  => false,
                'choices'   => $fieldChoices,
                'empty_value' => 'selecteer een veld',
                'constraints' =>  array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('country', 'choice', array(
                'label'         => 'Land',
                'required'  => false,
                'choices'   => $fieldChoices,
                'empty_value' => 'selecteer een veld',
                'constraints' =>  array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('lat', 'choice', array(
                'label'         => 'Lattitude / breedtegraad',
                'required'  => false,
                'choices'   => $fieldChoices,
                'empty_value' => 'selecteer een veld',
                'constraints' =>  array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('lon', 'choice', array(
                'label'         => 'Longitude / lengtegraad',
                'required'  => false,
                'choices'   => $fieldChoices,
                'empty_value' => 'selecteer een veld',
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
     * Also handles the mapping form and validates it and stores the data in the database
     * Sends user to test or standardize depending on params
     *
     * @param Application $app
     * @param integer $id
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function  mapCsv(Application $app,  Request $request, $id)
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

        $form = $this->getFieldMapForm($app, $columnNames);

        // if the form was posted
        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();

                // save the mapping
                $data['dataset_id'] = $id;
                if ($app['dataset_service']->storeFieldMapping($data)) { // ok
                    $app['session']->getFlashBag()->set('alert', 'De mapping is bewaard.');
                    //return $app->redirect($app['url_generator']->generate('dataset-showmapping', array('id' => $id)));
                    return $app->redirect($app['url_generator']->generate('standardize-test', array('id' => $id)));
                } else {
                    $app['session']->getFlashBag()->set('error', 'Sorry maar de velden konden niet bewaard worden.');
                    return $app->redirect($app['url_generator']->generate('import-mapcsv', array('id' => $id)));
                }
            }
        }

        // form or form errors
        return $app['twig']->render('import/field-mapper.twig', array(
            'columnNames' => $columnNames,
            'form'  => $form->createView()
        ));
    }

}