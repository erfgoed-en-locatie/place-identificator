<?php

namespace Pid\Mapper\Model;


/**
 * Status class
 * Holds the status that mapped records can have
 *
 * @package Pid\Mapper\Model
 */
class Status
{

    const UNMAPPED              = 0;
    const MAPPED_EXACT          = 1;
    const MAPPED_EXACT_MULTIPLE = 2;
    const MAPPED_EXACT_NOT_FOUND= 3;
    const MAPPED_MANUALLY       = 4;
    const UNMAPPABLE            = 99;

    /**
     * @var array
     */
    protected static $statusOptions = array(
        self::MAPPED_EXACT           => 'exacte match',
        self::MAPPED_EXACT_MULTIPLE  => 'meerdere matches',
        self::MAPPED_EXACT_NOT_FOUND => 'geen exacte match',
        self::UNMAPPED               => 'nog niet gemapped',
        self::MAPPED_MANUALLY        => 'handmatig gemapped',
        self::UNMAPPABLE             => 'niet te mappen'
    );

    public static function getFormattedOption($key)
    {
        $options = self::getStatusOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $key;
    }

    /**
     * @return array
     */
    public static function getStatusOptions()
    {
        return self::$statusOptions;
    }

}