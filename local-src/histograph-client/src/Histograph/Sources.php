<?php

namespace Histograph;

/**
 * Object that holds some of the available data sets (formerly known as sources) for Histograph.
 * Hardcoded, because we don not want to facilitate all obscure ones at the moment
 *
 * The list of available sources can also be fetched from the API directly
 *
 */
class Sources
{

    const TYPE_TGN = 'tgn';
    const TYPE_GENONAMES = 'geonames';
    const TYPE_GG = 'gemeentegeschiedenis';
    const TYPE_NWB = 'nwb';
    const TYPE_BAG = 'bag';
    const TYPE_KLOEKE = 'kloeke';
    const TYPE_HG = 'hg';

    private static $allSets = array(
        self::TYPE_TGN,
        self::TYPE_GENONAMES,
        self::TYPE_GG,
        self::TYPE_NWB,
        self::TYPE_BAG,
        self::TYPE_KLOEKE,

        'geonames-tgn',
        'militieregisters',
        'pleiades',
        'voc-opvarenden',
        'atlasverstedelijking',
        'verdwenen-dorpen',
        'simon-hart',
        'departementen1812',
        'ilvb',
    );

    private static $types = array(
        self::TYPE_TGN,
        self::TYPE_GENONAMES,
        self::TYPE_GG,
        self::TYPE_NWB,
        self::TYPE_BAG,
        self::TYPE_KLOEKE
    );

    public static function getAllSets()
    {
        natsort(self::$allSets);
        return array_combine(self::$allSets, self::$allSets);
    }

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

    /**
     * Find out what of what type of dataset/source a uri is
     *
     * @param $uri
     * @return string The name matches the column name of table.
     */
    public static function discoverSourceType($uri)
    {
        if (strpos($uri, 'geonames')) {
            return self::TYPE_GENONAMES;
        } else {
            if (strpos($uri, 'getty')) {
                return self::TYPE_TGN;
            } else {
                if (strpos($uri, 'gemeentegeschiedenis')) {
                    return self::TYPE_GG;
                } else {
                    if (strpos($uri, 'kadaster')) {
                        return self::TYPE_BAG;
                    } else {
                        return self::TYPE_HG;
                    }
                }
            }
        }
    }
}
