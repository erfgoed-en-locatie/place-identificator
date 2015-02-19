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

    /**
     * Fetch by status
     *
     * @param $id
     * @param $status
     * @return bool|string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchCountForDatasetWithStatus($id, $status)
    {
        $sql = '
          SELECT COUNT(*) AS aantal
          FROM records
          WHERE dataset_id = :id
          AND status = :status
          ';
        $params = array(
            'id' => (int) $id,
            'status' => $status
        );
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchColumn(0);
    }

    public function fetchFieldmappingForDataset($id)
    {
            $stmt = $this->db->executeQuery('SELECT * FROM field_mapping WHERE dataset_id = :id', array(
                'id' => (int) $id
            ));
        return $stmt->fetch();
    }

    public function fetchRecsWithStatus($id,$status)
    {
        $sql = 'SELECT * FROM records WHERE dataset_id = :id AND status = :status';
        $params = array(
            'id' => (int)$id,
            'status' => $status);
        $stmt = $this->db->executeQuery($sql, $params);
        
        return $stmt->fetchAll();
    }

    public function fetchRec($recid)
    {
        $sql = 'SELECT * FROM records WHERE id = :id';
        $params = array(
            'id' => (int)$recid);
        $stmt = $this->db->executeQuery($sql, $params);
        
        return $stmt->fetchAll();
    }
    

    /**
     * Save the provided mapping or update if it already exists
     *
     * @param array $data
     * @return int
     */
    public function storeFieldMapping($data)
    {
        $date = new \DateTime('now');

        if ($this->fetchFieldmappingForDataset($data['dataset_id'])) {
            $data['updated_on'] = $date->format('Y-m-d H:i:s');
            return $this->db->update('field_mapping', $data, array('dataset_id' => $data['dataset_id']));
        }

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
    public function storeMappedRecord($data)
    {
        $date = new \DateTime('now');
        $data['created_on'] = $date->format('Y-m-d H:i:s');

        return $this->db->insert('records', $data);
    }


    /**
     * Transform the result from the API into storable data and store that data
     *
     * If the dataset was standardize before, als delete all old mappings
     * @param array $mappedRows
     * @param string $placeColumn
     * @param boolean $deleteOld Whether to delete previously standardized data
     * @return integer $datasetId
     */
    public function storeMappedRecords($mappedRows, $placeColumn, $datasetId, $deleteOld = true)
    {
        if ($deleteOld === true) {
            $this->db->delete('records', array('dataset_id' => $datasetId));
        }

        foreach($mappedRows as $mapped) {
            $data = array();
            $data['original_name'] = $mapped[$placeColumn];
            $data['dataset_id'] = $datasetId;

            if ($mapped['response']['hits'] == 1) {
                $data['status'] = Status::MAPPED_EXACT;
                $data['hits'] = 1;
                if (isset($mapped['response']['data']['geonames'])) {
                    $data['geonames'] = json_encode($mapped['response']['data']['geonames']);
                }
                if (isset($mapped['response']['data']['tgn'])) {
                    $data['tgn'] = json_encode($mapped['response']['data']['tgn']);
                }
                if (isset($mapped['response']['data']['bag'])) {
                    $data['bag'] = json_encode($mapped['response']['data']['bag']);
                }
                if (isset($mapped['response']['data']['gemeentegeschiedenis'])) {
                    $data['gg'] = json_encode($mapped['response']['data']['gemeentegeschiedenis']);
                }
            } elseif ($mapped['response']['hits'] == 0) {
                $data['hits'] =  $mapped['response']['hits'];
                $data['status'] = Status::MAPPED_EXACT_NOT_FOUND;

            } else {
                $data['hits'] =  $mapped['response']['hits'];
                $data['status'] = Status::MAPPED_EXACT_MULTIPLE;
            }

            $this->storeMappedRecord($data);
        }

        return true;
    }

}