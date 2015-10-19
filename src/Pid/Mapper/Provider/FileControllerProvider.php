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

        $controllers->get('/view-csv/{filename}', array(new self(), 'viewCsv'))
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
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function viewCsv(Application $app, $filename)
    {
        $file = $app['upload_dir'] . '/' . $filename;

        $csv = \League\Csv\Reader::createFromPath($file);
        $csv->setDelimiter(current($csv->detectDelimiterList(2)));
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