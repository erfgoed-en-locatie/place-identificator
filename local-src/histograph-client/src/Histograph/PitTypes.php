<?php

namespace Histograph;

/**
 * Object that holds the available types for a Histograph PiT.
 * Hardcoded for now
 *
 * @see https://github.com/histograph/schemas/blob/master/json/pits.schema.json
 *
 */
final class PitTypes
{

    private static $types = array(
        'hg:Address', 'hg:Monument', 'hg:Building', 'hg:Fort', 'hg:Street', 'hg:Neighbourhood',
        'hg:Borough', 'hg:Place', 'hg:Municipality', 'hg:Water', 'hg:Polder', 'hg:Area', 'hg:Region', 'hg:Province',
        'hg:Baljuwschap', 'hg:Barony', 'hg:Departement', 'hg:Countship', 'hg:Heerlijkheid', 'hg:Country'
    );

    public static function getTypes()
    {
        natsort(self::$types);
        return array_combine(self::$types, self::$types);
    }

    public static function isValid($type)
    {
        if (in_array($type, self::$types)) {
            return true;
        }

        return false;
    }
}

