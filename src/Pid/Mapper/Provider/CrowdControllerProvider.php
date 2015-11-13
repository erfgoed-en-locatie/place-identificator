<?php

namespace Pid\Mapper\Provider;


use Pid\htmlTable;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class CrowdControllerProvider implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/view', array(new self(), 'viewAll'))
            ->bind('crowd-all')
            ;

        return $controllers;
    }

    /**
     * View all the crowd sourced input
     *
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function viewAll(Application $app)
    {

        $stmt = $app['db']->executeQuery('
          SELECT c.original_name, c.hg_id, c.hg_uri, hg_name, hg_geometry, c.hg_type, c.hg_dataset, c.created_on, d.name as dataset, u.email
          FROM crowd_mapping c
          INNER JOIN datasets d ON d.id = c.dataset_id
          INNER JOIN users u ON u.id = d.user_id
          ORDER BY d.id
           ');

        $records = $stmt->fetchAll();

        $table = htmlTable::createTable($records, 'ding', 'table table-striped table-bordered table-hover');
        return $app['twig']->render('crowd/all.html.twig', array(
            'table'   => $table

        ));
    }

}