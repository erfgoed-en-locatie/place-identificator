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

        $controllers->get('/view-csv/{filename}/{id}', array(new self(), 'viewCsv'))
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
     * @param $filename
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function viewCsv(Application $app, $filename, $id)
    {
        $dataset = $app['dataset_service']->fetchDataset($id, $app['user']->getId());
        if (!$dataset) {
            $app['session']->getFlashBag()->set('alert', 'Sorry maar die dataset bestaat niet.');

            return $app->redirect($app['url_generator']->generate('datasets-all'));
        }

        $file = $app['upload_dir'] . '/' . $filename;

        $csv = \League\Csv\Reader::createFromPath($file);
        if (0 < mb_strlen($dataset['delimiter'])) {
            $csv->setDelimiter($dataset['delimiter']);
        } else {
            $csv->setDelimiter(current($csv->detectDelimiterList(2)));
        }
        if (0 < mb_strlen($dataset['enclosure_character'])) {
            $csv->setEnclosure($dataset['enclosure_character']);
        }
        if (0 < mb_strlen($dataset['escape_character'])) {
            $csv->setEscape($dataset['escape_character']);
        }

        $rows =
            $csv->setOffset(0)
                ->fetchAll();

        $html = '<table class="table table-striped table-hover">';
        $html .= '<thead><tr>';
        foreach ($rows[0] as $headercolumn) {
            $html .= '<th>' . $headercolumn . '</th>';
        }
        $html .= '</tr></thead>';
        foreach($rows as $line) {
            $html .= '<tbody><tr>';
            foreach ($line as $cell) {
                $html .= '<td>' . $cell . '</td>';
            }
            $html .= '</tr></tbody>';
        }
        $html .= '</table>';

        return $app['twig']->render('datasets/csv.view.html.twig', array(
            'filename'  => $filename,
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