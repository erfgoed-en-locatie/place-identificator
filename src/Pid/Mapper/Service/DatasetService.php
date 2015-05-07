<?php

namespace Pid\Mapper\Service;
use Pid\Mapper\Model\DatasetStatus;
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
     * Fetch dataset details with Fieldmappings
     *
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchDatasetDetails($id)
    {
        $stmt = $this->db->executeQuery('SELECT d.*, f.fuzzy_search, f.search_option, f.identifier
          FROM datasets d
          INNER JOIN field_mapping f ON f.dataset_id = d.id
          WHERE d.id = :id', array(
            'id' => (int)$id
        ));

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
          AND status IN (' . implode(",",$status) . ')
          ';
        $params = array(
            'id' => (int) $id
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
        $sql = 'SELECT * FROM records WHERE dataset_id = :id AND status IN (' . implode(",",$status) . ')';
        $params = array(
            'id' => (int)$id);
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

    public function fetchRecs($setid)
    {
        $sql = 'SELECT * FROM records WHERE dataset_id = :id';
        $params = array(
            'id' => (int)$setid);
        $stmt = $this->db->executeQuery($sql, $params);
        
        return $stmt->fetchAll();
    }

    /**
     * Clear the results for a record
     *
     * @param integer $id
     * @return int
     * @internal param $data
     */
    public function clearRecord($id)
    {
        //$data['status'] = Status::UNMAPPED;
        $data['status'] = Status::MAPPED_EXACT_NOT_FOUND;
        $data['geonames'] = null;
        $data['tgn'] = null;
        $data['bag'] = null;
        $data['gg'] = null;
        $data['hits'] = 0;
        return $this->db->update('records', $data, array('id' => $id));
    }

    /**
     * Mark the record as unmappable so a user can skip it in the interface
     *
     * @param $id
     * @return int
     */
    public function setRecordAsUnmappable($id)
    {
        $data['status'] = Status::UNMAPPABLE;
        $data['geonames'] = null;
        $data['tgn'] = null;
        $data['bag'] = null;
        $data['gg'] = null;
        $data['hits'] = 0;
        return $this->db->update('records', $data, array('id' => $id));
    }

    /**
     * Save the provided mapping or update if it already exists
     * Also update the status of the dataset to mapped
     *
     * @param array $data
     * @return int
     */
    public function storeFieldMapping($data)
    {
        $date = new \DateTime('now');
        $this->db->update('datasets', array('status' => DatasetStatus::STATUS_FIELDS_MAPPED), array(
            'id' => $data['dataset_id'],
            'status' => DatasetStatus::STATUS_NEW
        ));

        if ($this->fetchFieldmappingForDataset($data['dataset_id'])) {
            $data['updated_on'] = $date->format('Y-m-d H:i:s');
            return $this->db->update('field_mapping', $data, array('dataset_id' => $data['dataset_id']));
        }

        $data['created_on'] = $date->format('Y-m-d H:i:s');
        return $this->db->insert('field_mapping', $data);
    }

    public function setMappingStarted($datasetId)
    {
        return $this->db->update('datasets', array('status' => DatasetStatus::STATUS_BEING_MAPPED), array(
            'id' => $datasetId
        ));
    }

    public function setMappingFinished($datasetId)
    {
        return $this->db->update('datasets', array('status' => DatasetStatus::STATUS_MAPPED), array(
            'id' => $datasetId
        ));
    }

    public function setMappingFailed($datasetId)
    {
        return $this->db->update('datasets', array('status' => DatasetStatus::STATUS_MAPPING_FAILED), array(
            'id' => $datasetId
        ));
    }

    /**
     * Fetch the user supplied configs for this dataset
     *
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getFieldMappingForDataset($id)
    {
        $stmt = $this->db->executeQuery('SELECT placename, search_option, identifier FROM field_mapping WHERE dataset_id = :id', array(
            'id' => (int)$id
        ));
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getUser($id)
    {
        $stmt = $this->db->executeQuery('SELECT name, email FROM users WHERE id = :id', array(
            'id' => (int)$id
        ));
        return $stmt->fetch(\PDO::FETCH_ASSOC);
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
     * Save the manually mapped record
     * Will now update all records with the same original name
     *
     * @param array $data
     * @param integer $id
     * @return int
     */
    public function storeManualMapping($data, $id)
    {
        $crowdData = $data;

        $date = new \DateTime('now');
        $data['updated_on'] = $date->format('Y-m-d H:i:s');
        $data['status'] = Status::MAPPED_MANUALLY;
        $data['hits'] = 0;

        $stmt = $this->db->executeQuery('SELECT original_name as name, dataset_id FROM records WHERE id = :id', array(
            'id' => (int) $id
        ));
        $stored = $stmt->fetch();

        // also save the mapping into the crowd table
        $crowdData['created_on'] = $date->format('Y-m-d H:i:s');
        $crowdData['dataset_id'] = $stored['dataset_id'];
        unset ($crowdData['identifier']);
        $this->db->insert('crowd_mapping', $crowdData);

        // grmbl, need to fetch the ids first, in order to be able to delete the rows in the ajaxy interface
        $statement = $this->db->executeQuery('SELECT id FROM records WHERE original_name = ?', array($stored['name']));
        //$ids = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $ids = $statement->fetchAll();

        $this->db->update('records', $data, array('original_name' => $stored['name']));
        return $ids;
    }

    /**
     * Transform the result from the API into storable data and store that data
     *
     * If the data set was standardized before, als delete all old mappings
     * @param array $mappedRows
     * @param string $placeColumn
     * @param boolean $deleteOld Whether to delete previously standardized data
     * @return integer $datasetId
     */
    public function storeMappedRecords($mappedRows, $datasetId, $placeColumn, $identifierColumn = null, $deleteOld = true)
    {
        if ($deleteOld === true) {
            $this->db->delete('records', array('dataset_id' => $datasetId));
        }

        foreach($mappedRows as $mapped) {
            $data = array();
            $data['original_name'] = $mapped[$placeColumn];
            $data['dataset_id'] = $datasetId;
            if (null !== $identifierColumn) {
                $data['identifier'] = $mapped[$identifierColumn];
            }

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