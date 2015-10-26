<?php

namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\DatasetStatus;
use Pid\Mapper\Model\Status;
use Pid\Mapper\Service\DatasetService;
use Silex\Application;
use Silex\ControllerProviderInterface;

use Silex\Provider\FormServiceProvider;
use SimpleUser\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use League\Csv\Writer;
use SplTempFileObject;

/**
 * Class DataSetControllerProvider
 * List datasets (for a certain user)
 *
 */
class DataSetControllerProvider implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/', array(new self(), 'showAll'))->bind('datasets-all');

        $controllers->get('/{id}', array(new self(), 'showDataset'))->bind('datasets-show')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/delete', array(new self(), 'deleteSet'))->bind('dataset-delete')->value('id', null)->assert('id', '\d+');

        $controllers->get('/{id}/standardized', array(new self(), 'showStandardized'))->bind('dataset-standardized')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/multiples', array(new self(), 'showMultiples'))->bind('dataset-multiples')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/multiples/{recid}', array(new self(), 'showMultipleRec'))->bind('dataset-multiple-rec')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/noresults', array(new self(), 'showNoResults'))->bind('dataset-noresults')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/unmappables', array(new self(), 'showUnmappables'))->bind('dataset-unmappables')->value('id', null)->assert('id', '\d+');

        $controllers->get('/{id}/create-download', array(new self(), 'createDownload'))->bind('dataset-create-download')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/download', array(new self(), 'doDownload'))->bind('dataset-download-file')->value('id', null)->assert('id', '\d+');

        $controllers->post('/{id}/choose-pit/{recordId}',
            array(new self(), 'choosePit'))->bind('record-choose-pit')->assert('id', '\d+');
        return $controllers;
    }

    /**
     * List all datasets for the logged in user
     *
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function showAll(Application $app)
    {
        /** @var \Doctrine\DBAL\Connection $db */
        $db = $app['db'];

        /** @var User $user */
        $user = $app['user'];

        $stmt = $db->prepare("
            SELECT d.*, u.email
            FROM datasets d
            INNER JOIN users u ON u.id = d.user_id
            WHERE user_id = :user_id");
        $stmt->execute(array('user_id' => $user->getId()));
        $datasets = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
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

        $stmt = $db->prepare("
          SELECT d.*, u.email
          FROM datasets d
          LEFT JOIN user u ON u.id = d.user_id
          where status != :status");
        $stmt->execute(array('status' => DatasetStatus::STATUS_FINISHED));
        $datasets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
        $dataset = $app['dataset_service']->fetchDatasetDetails($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }
        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        $downloadable = false;
        $newfile = $app['upload_dir'] . DIRECTORY_SEPARATOR . 'download_' . $dataset['id'];
        if (file_exists($newfile)) {
            $downloadable = true;
        }

        return $app['twig']->render('datasets/details.html.twig', array(
            'dataset' => $dataset,
            'downloadable' => $downloadable
        ));
    }


    /**
     * Delete a dataset and all it's data
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteSet(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');
            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $dataset['filename'];

        unlink($file);
        $app['db']->delete('datasets', array('id' => $id, 'user_id' => $app['user']->getId()));

        $app['session']->getFlashBag()->set('alert', 'De dataset is verwijderd!');

        return $app->redirect($app['url_generator']->generate('datasets-all'));
    }

    /**
     * Show all standardized recs
     *
     * @param Application $app
     * @param $id
     */
    public function showStandardized(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        $standardized = $app['dataset_service']->fetchRecsWithStatus($id, array(Status::MAPPED_MANUALLY, Status::MAPPED_EXACT));

        return $app['twig']->render('datasets/standardized.twig', array('dataset' => $dataset, "standardized" => $standardized));
    }

    /**
     * Show all multiple recs
     *
     * @param Application $app
     * @param $id
     */
    public function showMultiples(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        $multiples = $app['dataset_service']->fetchRecsWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        
        return $app['twig']->render('datasets/multiples.twig', array('dataset' => $dataset, "multiples" => $multiples));
    }

    /**
     * Show rec with multiple options
     *
     * @param Application $app
     * @param $id
     */
    public function showMultipleRec(Application $app, $id, $recid)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        $recs = $app['dataset_service']->fetchRec($recid);

        $fieldMapping = $app['dataset_service']->getFieldMappingForDataset($id);
        $possibilities = $app['geocoder_service']->fetchFeaturesForNamesWithMultipleHits($recs[0], $fieldMapping);

        return $app['twig']->render('datasets/multiple.twig', array(
            'dataset' => $dataset,
            "rec" => $recs[0],
            "possibilities" => $possibilities
        ));

    }

    /**
     * Normal POST for manually selecting a PIT,
     * needed because we can not post all the geormety data through ajax (too big)
     *
     * @param Application $app
     * @param Request $request
     * @param integer $id Id of the dataset
     * @param $recordId Id of the record to update
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function choosePit(Application $app, Request $request, $id, $recordId)
    {
        $jsonData = json_decode($request->request->get('data-klont'));
        $data = [];
        $pit = $jsonData->properties->pits[0];

        if (property_exists($pit, 'id')) {
            $data['hg_id'] = $pit->id;
        }
        $data['hg_uri'] = $pit->uri;
        $data['hg_name'] = $pit->name;
        $data['hg_type'] = $pit->type;
        $data['hg_dataset'] = $pit->dataset;
        if ($pit->geometryIndex > -1) {
            $data['hg_geometry'] = json_encode($jsonData->geometry->geometries[$pit->geometryIndex]);
        }

        if ($app['dataset_service']->storeManualMapping($data, $recordId)) {
            $app['session']->getFlashBag()->set('alert', 'De gekozen locatie is bewaard!');
        } else {
            $app['session']->getFlashBag()->set('error', 'Er ging iets mis bij het opslaan. Probeer svp opnieuw!');
        }

        return $app->redirect($app['url_generator']->generate('dataset-multiples', array('id' => $id)));
    }

    /**
     * Show all recs without results
     *
     * @param Application $app
     * @param $id
     */
    public function showNoResults(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        $noresults = $app['dataset_service']->fetchRecsWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        
        return $app['twig']->render('datasets/noresults.twig', array('dataset' => $dataset, "noresults" => $noresults));
    }


    /**
     * Show all recs without results
     *
     * @param Application $app
     * @param $id
     */
    public function showUnmappables(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        $unmappables = $app['dataset_service']->fetchRecsWithStatus($id, array(Status::UNMAPPABLE));
        
        return $app['twig']->render('datasets/unmappables.twig', array('dataset' => $dataset, "unmappables" => $unmappables));
    }


    /**
     * (Re-)Create a downloadable file
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createDownload(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        exec('php ../bin/pid standardize ' . $id . ' --method=download > /dev/null &');
        $app['session']->getFlashBag()->set('alert',
            'Het maken van de download-csv is begonnen en kan enige tijd duren. Refresh deze pagina over een paar minuten.');

        return $app->redirect($app['url_generator']->generate('datasets-show', ['id' => $id]));
    }

    /**
     * Run the download command or simply display the file if it exists already
     *
     * @param Application $app
     * @param Request $request
     * @param integer $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function doDownload(Application $app, Request $request,$id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        // check if file_exists
        $newfile = $app['upload_dir'] . DIRECTORY_SEPARATOR . 'download_' . $dataset['id'];
        if (file_exists($newfile)) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="download_' . $dataset['name'] . '.csv"');
            print file_get_contents($newfile);
            die;
        }
        $app['session']->getFlashBag()->set('error',
            'Downloaden is mislukt omdat het csv-bestand niet bestaat.');
        return $app->redirect($app['url_generator']->generate('datasets-show', ['id' => $id]));

    }

}