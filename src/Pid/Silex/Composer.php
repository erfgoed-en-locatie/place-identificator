<?php

namespace Pid\Silex;

class Composer
{
    /**
     * Stuff to do after composer install
     */
    private static $storageDirs = array(
        'app/storage/cache',
        'app/storage/doctrine',
        'app/storage/log'
    );

    /**
     * Create necessary storage directories
     */
    public static function install()
    {
        foreach (self::$storageDirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777);
            }
        }

        exec('php console cache:clear');
    }

    /**
     * Check to see if the dirs are still there and are writable
     */
    public static function update()
    {
        foreach (self::$storageDirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777);
            }
            if (!is_writable($dir)) {
                chmod($dir, 0777);
            }
        }

        exec('php console cache:clear');
    }

}
