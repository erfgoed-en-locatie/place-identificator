<?php

namespace Pid\Model;

/**
 * Object that holds some of the available dataset (formerly known as sources) for Histograph.
 * Hardcoded, because we don not want to facilittate all obscure ones at the moment
 *
 * The list of avalaible sources can also be fetched from the API directly
 *
 */
class Sources
{
    private static $types = array(
        'tgn', 'geonames', 'gemeentegeschiedenis', 'nwb', 'bag'
    );

    public static function getTypes()
    {
        natsort(self::$types);
        return self::$types;
    }

    public static function isValid($type)
    {
        if (in_array($type, self::$types)) {
            return true;
        }

        return false;
    }
}
