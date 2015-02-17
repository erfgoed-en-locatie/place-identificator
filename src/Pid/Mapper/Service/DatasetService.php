<?php

namespace Pid\Mapper\Service;


/**
 * Service or manager for the Dataset data
 *
 * @package Pid\Mapper\Service
 */
class DatasetService {

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Fetch a dataset by id and optionally also check on User
     *
     * @param $id
     * @param null $userId
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchDataset($id, $userId = null)
    {
        if (null === $userId) {
            $stmt = $this->db->executeQuery('SELECT * FROM datasets WHERE id = :id', array(
                'id' => (int)$id
            ));
        } else {
            $stmt = $this->db->executeQuery('SELECT * FROM datasets WHERE id = :id AND user_id = :userId', array(
                'id' => (int)$id,
                'userId' => (int)$userId
            ));
        }
        return $stmt->fetch();
    }

    /**
     * Save the provided mapping
     *
     * @param array $data
     * @return int
     */
    public function storeFieldMapping($data)
    {
        $date = new \DateTime('now');
        $data['created_on'] = $date->format('Y-m-d H:i:s');

        return $this->db->insert('field_mapping', $data);
    }

    public function getPlaceColumnForDataset($id)
    {
        $stmt = $this->db->executeQuery('SELECT placename FROM field_mapping WHERE id = :id', array(
            'id' => (int)$id
        ));
        return $stmt->fetchColumn(0);
    }

}