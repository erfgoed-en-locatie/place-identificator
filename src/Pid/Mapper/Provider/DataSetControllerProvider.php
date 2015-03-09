<?php

namespace Pid\Mapper\Provider;

use Pid\Mapper\Model\Dataset;
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

        //$controllers->get('/active', array(new self(), 'showActive'))->bind('datasets-active');
        $controllers->get('/', array(new self(), 'showAll'))->bind('datasets-all');

        $controllers->get('/{id}', array(new self(), 'showDataset'))->bind('datasets-show')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/delete', array(new self(), 'deleteSet'))->bind('dataset-delete')->value('id', null)->assert('id', '\d+');
        $controllers->get('/fieldmap/{id}', array(new self(), 'showMapping'))->bind('dataset-showmapping')->assert('id', '\d+');

        $controllers->get('/{id}/standardized', array(new self(), 'showStandardized'))->bind('dataset-standardized')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/multiples', array(new self(), 'showMultiples'))->bind('dataset-multiples')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/multiples/{recid}', array(new self(), 'showMultipleRec'))->bind('dataset-multiple-rec')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/noresults', array(new self(), 'showNoResults'))->bind('dataset-noresults')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/unmappables', array(new self(), 'showUnmappables'))->bind('dataset-unmappables')->value('id', null)->assert('id', '\d+');
        $controllers->get('/{id}/download', array(new self(), 'showDownload'))->bind('dataset-downloads')->value('id', null)->assert('id', '\d+');
        
        $controllers->post('/{id}/download', array(new self(), 'doDownload'))->bind('dataset-downloadcsv')->value('id', null)->assert('id', '\d+');
        
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
        $stmt->execute(array('status' => Dataset::STATUS_FINISHED));
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
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }
        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        return $app['twig']->render('datasets/details.html.twig', array('dataset' => $dataset));
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
     * Shows an example of the way the fields are mapped for the first x records of the csv file
     *
     * @param Application $app
     * @param $id
     */
    public function showMapping(Application $app, $id)
    {
        // todo show how the fields were mapped
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

        //print_r($standardized);
        for ($i=0; $i<count($standardized); $i++) {
            $standardized[$i]['geonames'] = json_decode($standardized[$i]['geonames']);
            $standardized[$i]['tgn'] = json_decode($standardized[$i]['tgn']);
            $standardized[$i]['gg'] = json_decode($standardized[$i]['gg']);
        }
        
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
        $rec = $recs[0];

        $possibilities = $app['geocoder_service']->mapOne($rec['original_name']);


        return $app['twig']->render('datasets/multiple.twig', array(
            'dataset' => $dataset,
            "rec" => $rec,
            "possibilities" => $possibilities
        ));

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
     * Show downloadpage for this dataset
     *
     * @param Application $app
     * @param $id
     */
    public function showDownload(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        $dataset['countStandardized'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT, Status::MAPPED_MANUALLY));
        $dataset['countMultiples'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_MULTIPLE));
        $dataset['countNoResults'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::MAPPED_EXACT_NOT_FOUND));
        $dataset['countUnmappables'] = $app['dataset_service']->fetchCountForDatasetWithStatus($id, array(Status::UNMAPPABLE));

        return $app['twig']->render('datasets/download.twig', array('dataset' => $dataset));
    }


    /**
     * Show downloadpage for this dataset
     *
     * @param Application $app
     * @param $id
     */
    public function doDownload(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id);
        if (!$dataset) {
            $app->abort(404, "Dataset with id ($id) does not exist.");
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        
        $fieldnames = array('original_name');
        
        // TODO: get user's placename identifier (if user told us we should include one)

        foreach ($_POST as $key => $value) {
            $fieldnames[] = $key;
        }
        $csv->insertOne($fieldnames);

        // get records in dataset
        $records = $app['dataset_service']->fetchRecs($id);

        for($i=0; $i < count($records); $i++) {

            // split up jsonblobs for geonames, tgn and gemeentegeschiedenis
            if($records[$i]['geonames']!=""){
                $geonames = json_decode($records[$i]['geonames']);
                $records[$i]['geonames-uri'] = $geonames->uri;
                $records[$i]['geonames-label'] = $geonames->name;
                $records[$i]['geonames-geometry'] = json_encode($geonames->geometry);
            }else{
                $records[$i]['geonames-uri'] = "";
                $records[$i]['geonames-label'] = "";
                $records[$i]['geonames-geometry'] = "";
            }
            if($records[$i]['tgn']!=""){
                $tgn = json_decode($records[$i]['tgn']);
                $records[$i]['tgn-uri'] = $tgn->uri;
                $records[$i]['tgn-label'] = $tgn->name;
                $records[$i]['tgn-geometry'] = json_encode($tgn->geometry);
            }else{
                $records[$i]['tgn-uri'] = "";
                $records[$i]['tgn-label'] = "";
                $records[$i]['tgn-geometry'] = "";
            }
            if($records[$i]['gg']!=""){
                $gg = json_decode($records[$i]['gg']);
                $records[$i]['gg-uri'] = $gg->uri;
                $records[$i]['gg-label'] = $gg->name;
                $records[$i]['gg-geometry'] = json_encode($gg->geometry);
            }else{
                $records[$i]['gg-uri'] = "";
                $records[$i]['gg-label'] = "";
                $records[$i]['gg-geometry'] = "";
            }

            $wanted = array();
            foreach ($fieldnames as $field) {
                $wanted[$field] = $records[$i][$field];
            }
            
            $csv->insertOne($wanted);
        }

        
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $dataset['name'] . '.csv"');
        $csv->output();

        return '';
        
    }


}