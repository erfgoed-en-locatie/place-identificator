<?php

namespace Pid\Mapper\Model;

/**
 * Status class
 * Holds the status that mapped records can have
 *
 * @package Pid\Mapper\Model
 */
class DatasetStatus {

    const STATUS_NEW            = 1;
    const STATUS_FIELDS_MAPPED  = 2;
    const STATUS_BEING_MAPPED   = 3;
    const STATUS_MAPPED         = 9;
    const STATUS_MAPPING_FAILED = 33;
    const STATUS_FINISHED       = 99;

    /**
     * @var array
     */
    protected static $statusOptions = array(
        self::STATUS_NEW            => 'nieuw',
        self::STATUS_FIELDS_MAPPED  => 'velden benoemd en test gedaan',
        self::STATUS_BEING_MAPPED   => 'mapping begonnen',
        self::STATUS_MAPPED         => 'mapping afgerond',
        self::STATUS_MAPPING_FAILED => 'mapping mislukt',
        self::STATUS_FINISHED       => 'klaar'
    );

    /**
     * @return array
     */
    public static function getStatusOptions()
    {
        return self::$statusOptions;
    }

}