<?php

namespace Pid\Mapper\Provider;


use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class FileControllerProvider implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/view-csv/{id}', array(new self(), 'viewCsv'))
            ->bind('file-view-csv')
            ;
        $controllers->get('/download-csv/{filename}', array(new self(), 'downloadCsv'))
            ->bind('file-download-csv')
            ->assert('filename', '\d+');

        return $controllers;
    }

    /**
     * View the csv
     *
     * @param Application $app
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function viewCsv(Application $app, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        $html = $app['csv_service']->convertCsv2Html($dataset);

        return $app['twig']->render('datasets/csv.view.html.twig', array(
            'dataset'   => $dataset,
            'table'     => $html
        ));
    }


    public function downloadCsv(Application $app, $filename)
    {
        $file = $app['upload_dir'] . '/' . $filename;
        $stream = function () use ($file) {
            readfile($file);
        };

        return $app->stream($stream, 200, array(
            'Content-Type' => 'text/csv',
            'Content-length' => filesize($file),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ));

    }

}