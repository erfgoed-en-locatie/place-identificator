<?php

namespace Pid\Mapper\Model;

/**
 * Status class
 * Holds the different statusses that a mapped records can have
 *
 * @package Pid\Mapper\Model
 */
class Status {

    const UNMAPPED                = null;
    const MAPPED_EXACT            = 1;
    const MAPPED_EXACT_MULTIPLE   = 2;
    const MAPPED_EXACT_NOT_FOUND  = 3;

    /**
     * @var array
     */
    protected $statusOptions = array(
        self::MAPPED_EXACT            => 'exacte match',
        self::MAPPED_EXACT_MULTIPLE   => 'meer dan een match',
        self::MAPPED_EXACT_NOT_FOUND  => 'geen exacte match',
        self::UNMAPPED                => 'nog niet gemapped',
    );

    /**
     * @return array
     */
    public function getStatusOptions()
    {
        return $this->statusOptions;
    }

    /**
     * @param array $statusOptions
     */
    public function setStatusOptions($statusOptions)
    {
        $this->statusOptions = $statusOptions;
    }


    /**
     * Returns the formatted Status option
     * @param integer $key
     * @return mixed
     */
    public function getFormattedStatus($key)
    {
        if (isset($this->statusOptions[$key])) {
            return $this->statusOptions[$key];
        }
        return $key;
    }

}