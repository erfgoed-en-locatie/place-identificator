<?php
/**
 * User: @PHPetra
 * Date: 05/02/15
 * 
 */

namespace Pid\Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;

class MySQLiServiceProvider implements ServiceProviderInterface
{

    /**
     * Register
     *
     * @param Application $app
     */
    public function register(Application $app)
    {
        $app['mysqli'] = function () use ($app) {
            if (isset($app['mysqli.configuration']) && is_array($app['mysqli.configuration'])) {
                $config = $app['mysqli.configuration'];
            } else {
                throw new \LogicException('mysqli.configuration is not defined');
            }
            $MySQLi = new \mysqli($config['host'], $config['username'], $config['password'], $config['database']);
            $MySQLi->set_charset($config['charset']);
            return $MySQLi;
        };
    }

    /**
     * Register
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {

    }

}