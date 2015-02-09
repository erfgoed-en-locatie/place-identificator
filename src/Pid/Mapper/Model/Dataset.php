<?php

namespace Pid\Mapper\Model;

/**
 * Class Dataset
 *
 * @package Pid\Mapper\Model
 */
class Dataset implements \JsonSerializable {

    const STATUS_NEW        = 1;
    const STATUS_UNMAPPED   = 2;
    const STATUS_MAPPED     = 3;
    const STATUS_FINISHED   = 99;

    /**
     * @var array
     */
    protected $statusOptions = array(
        self::STATUS_NEW         => 'nieuw',
        self::STATUS_MAPPED      => 'gemapped',
        self::STATUS_UNMAPPED    => 'nog niet gemapped',
        self::STATUS_FINISHED    => 'afgerond'
    );

    private $id;
    private $name;
    private $filename;
    private $user_id;
    private $status;
    private $created_on;
    private $updated_on;


    /**
     * Simple object constructor
     */
    public function __construct()
    {
        $this->created_on = new \DateTime();
        //$this->user = 'Pietje';
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param mixed $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        // todo return the real user object
        return $this->user_id;
    }

    /**
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user_id = $user;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        if (array_key_exists($status, $this->statusOptions)) {
            $this->status = $status;
        }
    }

    /**
     * Returns the formatted Status option
     * @return mixed
     */
    public function getFormattedStatus()
    {
        if (isset($this->statusOptions[$this->status])) {
            return $this->statusOptions[$this->status];
        }
        return $this->status;
    }

    /**
     * @return mixed
     */
    public function getCreatedOn()
    {
        return $this->created_on;
    }

    /**
     * @param mixed $createdOn
     */
    public function setCreatedOn($createdOn)
    {
        $this->created_on = $createdOn;
    }

    /**
     * @return mixed
     */
    public function getUpdatedOn()
    {
        return $this->updated_on;
    }

    /**
     * @param mixed $updatedOn
     */
    public function setUpdatedOn($updatedOn)
    {
        $this->updated_on = $updatedOn;
    }

    /**
     * Simply expose all
     *
     * @return array|mixed
     */
    public function JsonSerialize()
    {
        $properties = array_keys(get_class_vars(__CLASS__));
        $out = array();

        foreach ($properties as $prop) {
            $method = 'get' . ucfirst($prop);
            $out[$prop] = $this->$method();
        }

        return $out;
    }

    public function simpleJsonSerialize()
    {
        $vars = get_object_vars($this);

        return $vars;
    }

}