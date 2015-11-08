<?php


namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\DatasetStatus;
use Pid\Mapper\Service\DatasetService;
use Pid\Mapper\Service\GeocoderService;
use Histograph\PitTypes;
use Histograph\Sources;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use SimpleUser\User;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ImportControllerProvider
 *
 */
class ImportControllerProvider implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/upload', array(new self(), 'uploadForm'))->bind('dataset-upload-form');
        $controllers->post('/upload', array(new self(), 'handleUpload'))->bind('dataset-upload');

        $controllers->match('/config/{id}', array(new self(), 'editCsvConfig'))
            ->bind('import-editcsv')->assert('id', '\d+')->method('GET|POST');


        $controllers->match('/mapcsv/{id}', array(new self(), 'mapCsv'))
            ->bind('import-mapcsv')->assert('id', '\d+')->method('GET|POST');

        return $controllers;
    }

    /**
     * Form for csv file uploads
     *
     * @param Application $app
     * @param null $data
     * @return mixed
     */
    private function getUploadForm(Application $app, $data = null)
    {
        $form = $app['form.factory']
            ->createBuilder('form', $data)
            ->add('name', 'text', array(
                'label' => 'Geef uw dataset een herkenbare naam',
                'required' => true,
                'constraints' => array(
                    new Assert\NotBlank(),
                    new Assert\Regex(array(
                        'pattern' => '/^[a-z0-9-\s]+$/i',
                        'htmlPattern' => '^[a-z0-9-\s]+$',
                        'match' => true,
                        'message' => 'Voer alleen letters of cijfers in',
                    )),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('delimiter', 'text', array(
                'label' => 'Wat is het scheidingsteken van de kolommen in uw dataset?',
                'required' => false,
                'constraints' => array(
                    new Assert\Length(array('min' => 1, 'max' => 2))
                ),
                'attr' => array(
                    'placeholder' => 'bv , of ; ',
                    'class'     => 'narrow'
                )
            ))
            /*->add('enclosure_character', 'text', array(
                'label' => 'Worden de velden "enclosed" door een bepaald teken?',
                'required' => false,
                'constraints' => array(
                    new Assert\Length(array('min' => 1, 'max' => 1))
                ),
                'attr' => array(
                    'class'     => 'narrow'
                )
            ))*/
            /*->add('escape_character', 'text', array(
                'label' => 'Is er een bepaald karakter dat "escaped" moet worden?',
                'required' => false,
                'constraints' => array(
                    new Assert\Length(array('min' => 1, 'max' => 1))
                ),
                'attr' => array(
                    'class'     => 'narrow'
                )
            ))*/
            ->add('skip_first_row', 'choice', array(
                'label' => 'Bevat de eerste rij de kolomnamen?',
                'required' => true,
                'choices' => array(1 => 'Ja', 0 => 'Nee'),
                'constraints' => array(
                    new Assert\Type('integer')
                )
            ))
            ->add('csvFile', 'file', array(
                'label' => 'Kies een csv-bestand op uw computer',
                'required' => true,
                'constraints' => array(
                    new Assert\NotBlank(),
                    new Assert\File(array(
                        'maxSize' => '40M',
                        'mimeTypes' => array('text/csv', 'text/plain'),
                    )),
                    new Assert\Type('file')
                )
            ))
            ->getForm();

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

            $filename = time() . '.csv';
            $originalName = $files['csvFile']->getClientOriginalName();
            $files['csvFile']->move($app['upload_dir'], $filename);

            $data = $form->getData();
            $date = new \DateTime('now');

            /** @var \Doctrine\DBAL\Connection $db */
            $db = $app['db'];
            $db->insert('datasets', array(
                'name' => $data['name'],
                'skip_first_row' => $data['skip_first_row'],
                'filename' => $filename,
                'original_name' => $originalName,
                'created_on' => $date->format('Y-m-d H:i:s'),
                'status' => DatasetStatus::STATUS_NEW,
                'user_id' => (int)$app['user']->getId()
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
    private function getFieldMapForm(Application $app, $fieldChoices, $mapping = false)
    {
        if (!$mapping) {
            $mapping = array(
                'geometry' => false,
            );
        }

        /** @var FormFactory $form */
        $form = $app['form.factory']
            ->createBuilder('form', $mapping)
            ->add('placename_column', 'choice', array(
                'label' => 'Toponiem in veld ',
                'choices' => $fieldChoices,
                'empty_value' => 'selecteer een veld',
                'required' => true,
                'constraints' => array(
                    new Assert\NotBlank(),
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('liesin_column', 'choice', array(
                'label' => 'Dit toponiem ligt in ...(provincie, plaats, etc.)',
                'required' => false,
                'choices' => $fieldChoices,
                'empty_value' => 'selecteer een veld',
                'constraints' => array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('hg_type', 'choice', array(
                'label' => 'Dit toponiem is van het type ',
                'required' => true,
                'choices' => PitTypes::getTypes(),
                'empty_value' => 'selecteer een veld',
                'constraints' => array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('hg_dataset', 'choice', array(
                'label' => 'Standaardiseer naar ',
                'required' => true,
                'choices' => Sources::getTypes(),
                'empty_value' => 'kies standaard',
                'constraints' => array(
                    new Assert\Length(array('min' => 1, 'max' => 123))
                )
            ))
            ->add('geometry', 'choice', array(
                'label' => 'Geïnteresseerd in de geometrie?',
                'required' => true,
                'choices' => array(1 => 'Ja', 0 => 'Nee'),
                'data' => 0,
                'constraints' => array(
                    new Assert\Type('integer')
                )
            ))
            ->add('save', 'submit', array(
                'label' => 'bewaar deze instellingen',
                'attr' => array('class' => 'btn btn-success'),
            ))
            ->add('map', 'submit', array(
                'label' => 'bewaar deze instellingen en test 20 records',
                'attr' => array('class' => 'btn btn-primary'),
            ))
            ->add('mapall', 'submit', array(
                'label' => 'bewaar en standaardiseer alle records',
                'attr' => array('class' => 'btn btn-danger'),
            ))

            ->getForm();

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
    public function mapCsv(Application $app, Request $request, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        // attempt to make sense of the csv file
        $columnNames = $app['csv_service']->getColumns($dataset);

        // see if we already have a mapping..
        $form = $this->getFieldMapForm($app, $columnNames, $dataset);

        // if the form was posted
        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();

                // save the mapping
                if ($app['dataset_service']->storeFieldMapping($data)) { // ok

                    // also copy the records to db
                    $app['dataset_service']->copyRecordsFromCsv($dataset);
                    $app['session']->getFlashBag()->set('alert', 'De instellingen zijn aangepast en opgeslagen.');

                    // and go straight to mapping all, if clicked
                    if ($form->get('mapall')->isClicked()) {
                        return $app->redirect($app['url_generator']->generate('standardize', array('id' => $id)));
                    } elseif ($form->get('save')->isClicked()) {
                        return $app->redirect($app['url_generator']->generate('datasets-all'));
                    } else {
                        return $app->redirect($app['url_generator']->generate('standardize-test', array('id' => $id)));
                    }
                } else {
                    $app['session']->getFlashBag()->set('error', 'Sorry maar de instellingen konden niet opgeslagen worden.');

                    return $app->redirect($app['url_generator']->generate('import-mapcsv', array('id' => $id)));
                }
            }
        }

        // form or form errors
        return $app['twig']->render('import/field-mapper.twig', array(
            'columnNames' => $columnNames,
            'form' => $form->createView(),
            'dataset' => $dataset
        ));
    }

    /**
     * Edit the config for the csv the name annd the delimiters
     *
     * @param Application $app
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function editCsvConfig(Application $app, Request $request, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');
            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        $form = $this->getUploadForm($app, $dataset);
        $form->remove('csvFile');

        // if the form was posted
        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();

                // save the mapping
                if ($app['dataset_service']->storeCSVConfig($data)) { // ok
                    $app['session']->getFlashBag()->set('alert', 'De instellingen zijn aangepast en opgeslagen.');
                    return $app->redirect($app['url_generator']->generate('datasets-all'));

                } else {
                    $app['session']->getFlashBag()->set('error', 'Sorry maar de instellingen konden niet opgeslagen worden.');

                    return $app->redirect($app['url_generator']->generate('import-mapcsv', array('id' => $id)));
                }
            }
        }

        // form or form errors
        return $app['twig']->render('import/editform.html.twig', array(
            'form' => $form->createView(),
            'dataset' => $dataset
        ));
    }

}