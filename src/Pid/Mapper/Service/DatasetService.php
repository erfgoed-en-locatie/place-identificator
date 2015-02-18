<?php

namespace Pid\Mapper\Service;
use Pid\Mapper\Model\Status;


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
            $sql = 'SELECT * FROM datasets WHERE id = :id AND user_id = :userId';
            $params = array(
                'id' => (int)$id,
                'userId' => (int)$userId);
            $stmt = $this->db->executeQuery($sql, $params);
        }
        return $stmt->fetch();
    }

    // todo finish this query
    public function fetchCountForDatasetWithStatus($id, $status)
    {
        $sql = '
          SELECT d.* COUNT(r.*)
          FROM records r
          JOIN datasets d ON d.id = r.dataset_id
          WHERE d.id = :id
          AND r.status = :status
          ';
        $params = array(
            'id' => (int) $id,
            'status' => $status
        );
        $stmt = $this->db->executeQuery($sql, $params);
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
        $stmt = $this->db->executeQuery('SELECT placename FROM field_mapping WHERE dataset_id = :id', array(
            'id' => (int)$id
        ));
        return $stmt->fetchColumn(0);
    }

    /**
     * Save the provided mapping
     *
     * @param array $data
     * @return int
     */
    public function storeMappedRecords($data)
    {
        $date = new \DateTime('now');
        $data['created_on'] = $date->format('Y-m-d H:i:s');

        return $this->db->insert('records', $data);
    }


}